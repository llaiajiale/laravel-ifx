<?php
/**
 * Created by PhpStorm.
 * User: llaijiale
 * Date: 2017/8/2
 * Time: 10:21
 */

return [
    'informix' => [
        'driver'    => 'informix',
        'host'      => env('DB_HOST', 'localhost'),
        'database'  => env('DB_DATABASE', 'forge'),
        'username'  => env('DB_USERNAME', 'forge'),
        'password'  => env('DB_PASSWORD', ''),
        'service'  => env('DB_SERVICE', '11143'),
        'server'  => env('DB_SERVER', ''),
        'db_locale'   => 'en_US.819',
        'client_locale' => 'en_US.819',
        'db_encoding'   => 'GBK',
        'initSqls' => false,
        'client_encoding' => 'UTF-8',
        'prefix'    => ''
    ],
];
