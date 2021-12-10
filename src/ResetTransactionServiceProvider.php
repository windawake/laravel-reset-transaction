<?php

namespace Laravel\ResetTransaction;

use Illuminate\Support\ServiceProvider;
use Laravel\ResetTransaction\Middleware\DistributeTransact;
use Laravel\ResetTransaction\Console\CreateExamples;
use Illuminate\Database\Connection;
use Laravel\ResetTransaction\Database\MySqlConnection;

class ResetTransactionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../examples/routes.php');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['router']->aliasMiddleware('distribute.transact', DistributeTransact::class);

        $this->app->singleton(
            'command.resetTransact.create-examples',
            function ($app) {
                return new CreateExamples($app['files']);
            }
        );

        $this->commands(
            'command.resetTransact.create-examples'
        );

        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            // Next we can initialize the connection.
            return new MySqlConnection($connection, $database, $prefix, $config);
        });
    }
}
