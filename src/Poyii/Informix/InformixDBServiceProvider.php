<?php
/**
 * Created by PhpStorm.
 * User: llaijiale
 * Date: 2017/8/2
 * Time: 10:40
 */

namespace Poyii\Informix;

use Illuminate\Support\ServiceProvider;
/**
 * Class InformixDBServiceProvider.
 */
class InformixDBServiceProvider extends ServiceProvider
{

    /**
     * Boot.
     */
    public function boot()
    {
        $this->publishes(
            [
                __DIR__.'/../../config/informix.php' => config_path('informix.php'),
            ]
        );
    }

    /**
     * Register the service provider.
     *
     * @returns \Poyii\Informix\IfxConnection
     */
    public function register()
    {
        if (file_exists(config_path('informix.php'))) {

            $this->mergeConfigFrom(config_path('informix.php'), 'database.connections');

            $config = $this->app['config']->get('informix', []);

            $connection_keys = array_keys($config);

            foreach ($connection_keys as $key) {
                $this->app['db']->extend($key, function ($config) {
                    $oConnector = new Connectors\IfxConnector($this->app['encrypter']);
                    $connection = $oConnector->connect($config);
                    return new IfxConnection($connection, $config['database'], $config['prefix'], $config);
                });
            }
        }
    }
}
