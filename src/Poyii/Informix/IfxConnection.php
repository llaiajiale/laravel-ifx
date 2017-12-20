<?php

namespace Poyii\Informix;
/**
 * Created by PhpStorm.
 * User: llaijiale
 * Date: 2016/1/20
 * Time: 14:34
 */
use Illuminate\Database\Connection;
use Poyii\Informix\Query\Processors\IfxProcessor;
use Poyii\Informix\Query\Grammars\IfxGrammar as QueryGrammar;
use Poyii\Informix\Schema\Grammars\IfxGrammar as SchemaGrammar;
use Poyii\Informix\Schema\IfxBuilder as SchemaBuilder;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

class IfxConnection extends Connection
{

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Illuminate\Database\Schema\MySqlBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }
        return new SchemaBuilder($this);
    }


    /**
     * Get the default post processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\SqlServerProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new IfxProcessor;
    }


    public function prepareBindings(array $bindings){
        $grammar = $this->getQueryGrammar();
        if($this->isTransEncoding()){
            $db_encoding = $this->getConfig('db_encoding');
            $client_encoding = $this->getConfig('client_encoding');
            foreach ($bindings as $key => &$value) {
                // We need to transform all instances of DateTimeInterface into the actual
                // date string. Each query grammar maintains its own date string format
                // so we'll just ask the grammar for the format to get from the date.
                if ($value instanceof DateTimeInterface) {
                    $value = $value->format($grammar->getDateFormat());
                } elseif ($value === false) {
                    $value = 0;
                }
                if(is_string($value)) {
                    $value = $this->convertCharset($client_encoding, $db_encoding, $value);
                }
            }
        } else {
            foreach ($bindings as $key => &$value) {
                if ($value instanceof DateTimeInterface) {
                    $value = $value->format($grammar->getDateFormat());
                } elseif ($value === false) {
                    $value = 0;
                }
            }
        }
        return $bindings;
    }

    protected function isTransEncoding(){
        $db_encoding = $this->getConfig('db_encoding');
        $client_encoding = $this->getConfig('client_encoding');
        return ($db_encoding && $client_encoding && ($db_encoding != $client_encoding));
    }

    protected function convertCharset($in_encoding, $out_encoding, $value){

        //IGNORE
//        $encoding = mb_detect_encoding($value, mb_detect_order(), false);
//
//        if($encoding == $out_encoding)
//        {
//            return $value;
//        }
//        Log::debug("encoding: ".$in_encoding." value ".$value);
        //return mb_convert_encoding(trim($value), $out_encoding);
        return iconv($in_encoding, "{$out_encoding}//IGNORE", trim($value));
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if(config("app.debug"))
            Log::debug("query: ".$query." with ".implode(', ', $bindings));
        $results = parent::select($query, $bindings, $useReadPdo);
        if($this->isTransEncoding()){
            if($results){
                $db_encoding = $this->getConfig('db_encoding');
                $client_encoding = $this->getConfig('client_encoding');
                if(is_array($results) || is_object($results)){
                    foreach($results as &$result){
                        if(is_array($result) || is_object($result)){
                            foreach($result as $key=>&$value){
                                if(is_string($value)){
                                    $value = $this->convertCharset($db_encoding, $client_encoding, $value);
                                }
                            }
                        } else if(is_string($result)) {
                            $result = $this->convertCharset($db_encoding, $client_encoding, $result);
                        }
                    }
                } else if(is_string($results)) {
                    $results = $this->convertCharset($db_encoding, $client_encoding, $results);
                }
            }
        }
        return $results;
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\SqlServerGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\SqlServerGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }


    public function statement($query, $bindings = [])
    {

        if(config("app.debug"))
            Log::debug("statement: ".$query." with ".implode(', ', $bindings));
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) {
                return true;
            }
            $count = substr_count($query, '?');
            if($count == count($bindings)){
                $bindings = $me->prepareBindings($bindings);
                return $me->getPdo()->prepare($query)->execute($bindings);
            }

            if(count($bindings) % $count > 0)
                throw new \InvalidArgumentException('the driver can not support multi-insert.');

            $mutiBindings = array_chunk($bindings, $count);
            $me->beginTransaction();
            try{
                $pdo = $me->getPdo();
                $stmt = $pdo->prepare($query);

                foreach($mutiBindings as $mutiBinding){
                    $mutiBinding = $me->prepareBindings($mutiBinding);
                    $stmt->execute($mutiBinding);
                }
            }catch(\Exception $e){
                $me->rollBack();
                return false;
            }catch(\Throwable $e){
                $me->rollBack();
                return false;
            }
            $me->commit();

            return true;

        });
    }

    public function affectingStatement($query, $bindings = [])
    {
        if(config("app.debug"))
            Log::debug("affectingStatement: ".$query." with ".implode(', ', $bindings));
        return parent::affectingStatement($query, $bindings);
    }


}