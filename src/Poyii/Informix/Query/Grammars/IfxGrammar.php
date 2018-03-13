<?php

namespace Poyii\Informix\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

/**
 * Created by PhpStorm.
 * User: llaijiale
 * Date: 2016/1/19
 * Time: 0:53
 */

class IfxGrammar extends Grammar {


    protected function compileLimit(Builder $query, $limit)
    {
        return '';
    }

    protected function compileOffset(Builder $query, $offset)
    {
        return '';
    }


    protected function compileColumns(Builder $query, $columns)
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (! is_null($query->aggregate)) {
            return;
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        if($query->offset > 0){
            $select.=' skip '. (int)$query->offset;
        }

        if ($query->limit > 0 ) {
            $select.= ' first '.(int)$query->limit;
        }


        return $select.' '.$this->columnize($columns);
    }

    protected function compileLock(Builder $query, $value)
    {
        if (is_string($value)) {
            return $value;
        }

        return $value ? ' for update' : ' for read only';
    }

    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        return str_replace('"', '', $value);
    }

    public function compileSelect(Builder $query)
    {
        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $components = $this->compileComponents($query);

        if(key_exists("lock", $components)){
            unset($components["orders"]);
        }

        return trim($this->concatenate($components));
    }

    protected function compileUnions(Builder $query)
    {
        $sql = '';

        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (isset($query->unionOrders)) {
            $sql .= ' '.$this->compileOrders($query, $query->unionOrders);
        }
        return ltrim($sql);
    }

    public function compileExists(Builder $query)
    {
        $existsQuery = clone $query;

        $existsQuery->columns = [];

        return $this->compileSelect($existsQuery->selectRaw('1 e'));
    }

    public function compileInsert(Builder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (! is_array(reset($values))) {
            $values = [$values];
        }
//        if(count($values) > 1)
//            throw new \InvalidArgumentException('the driver can not support multi-insert.');

        $values = reset($values);
        $columns = $this->columnize(array_keys($values));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same amount of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = [];

        $parameters[] = '('.$this->parameterize($values).')';
//        foreach ($values as $record) {
//            $parameters[] = '('.$this->parameterize($record).')';
//        }

        $parameters = implode(', ', $parameters);

        return "insert into {$table} ({$columns}) values {$parameters}";
    }

    protected function whereBitand(Builder $query, $where)
    {
        $bitand = $where['not'] ? 'not bitand' : 'bitand';
        $values = $where['values'];
        return $bitand.'('.$this->wrap($where['column']).', '.$this->wrapValue($values[0]).' ) '.$where['operator'].' '.$this->wrapValue($values[1]);

        //return $bitand.'('.$this->wrap($where['column']).',0) '.$where['operator'].' 0 ';
    }

}