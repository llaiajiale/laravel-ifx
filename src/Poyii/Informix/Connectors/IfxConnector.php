<?php

namespace Poyii\Informix\Connectors;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;
use Illuminate\Support\Arr;
use Exception;


class IfxConnector extends Connector implements ConnectorInterface
{
    protected $encrypter;

    /**
     * The PDO connection options.
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];

    /**
     * IfxConnector constructor.
     * @param $encrypter
     */
    public function __construct(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    public function createConnection($dsn, array $config, array $options)
    {
        $username = Arr::get($config, 'username');
        $password = Arr::get($config, 'password');

        if($this->encrypter && strlen($password) > 50){
            if(starts_with("base64:", $password)){
                $password = $this->encrypter->decrypt(substr($password, 7));
            } else {
                $password = $this->encrypter->decrypt($password);
            }
        }

        try {
            $pdo = new \PDO($dsn, $username, $password, $options);
        } catch (Exception $e) {
            $pdo = $this->tryAgainIfCausedByLostConnection(
                $e, $dsn, $username, $password, $options
            );
        }

        return $pdo;
    }


    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config)
    {
        $dsn = $this->getDsn($config);

        $options = $this->getOptions($config);

        // We need to grab the PDO options that should be used while making the brand
        // new connection instance. The PDO options control various aspects of the
        // connection's behavior, and some might be specified by the developers.
        $connection = $this->createConnection($dsn, $config, $options);

        if (Arr::get($config, 'initSqls', false)) {
            if(is_string($config['initSqls']))
                $connection->exec($config['initSqls']);
            if(is_array($config['initSqls'])){
                $connection->exec( implode('; ', $config['initSqls']) );
            }
        }

        return $connection;
    }

    /**
     * Create a DSN string from a configuration.
     *
     * Chooses socket or host/port based on the 'unix_socket' config value.
     *
     * @param  array   $config
     * @return string
     */
    protected function getDsn(array $config)
    {
        // informix:host=68.1.32.36; service=9021; database=gu5200car3gdb; server=gu_5200_cb_rss; protocol=onsoctcp
        return "informix:host={$config['host']}; database={$config['database']}; service={$config['service']}; server={$config['server']}; ".$this->getDsnOption($config);
    }

    protected function getDsnOption(array $config)
    {
        $options = "protocol=".Arr::get($config, "onsoctcp", "onsoctcp").";";

        if(isset($config['db_locale'])) $options.=" DB_LOCALE={$config['db_locale']};";
        if(isset($config['client_locale'])) $options.=" CLIENT_LOCALE={$config['client_locale']};";

        return $options;
    }
}
