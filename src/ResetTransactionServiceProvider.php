<?php

namespace Laravel\ResetTransaction;

use Illuminate\Support\ServiceProvider;
use Laravel\ResetTransaction\Middleware\DistributeTransact;
use Laravel\ResetTransaction\Console\CreateExamples;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Laravel\ResetTransaction\Console\AsyncCommit;
use Laravel\ResetTransaction\Database\MySqlConnection;
use Laravel\ResetTransaction\Facades\ResetTransaction;

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

        $this->app->singleton(
            'command.resetTransact:async-commit',
            function ($app) {
                return new AsyncCommit();
            }
        );

        $this->app->singleton('rt', function($app){
            return new ResetTransaction();
        });

        $this->commands([
            'command.resetTransact.create-examples',
            'command.resetTransact:async-commit',
        ]);

        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            // Next we can initialize the connection.
            $connection = new MySqlConnection($connection, $database, $prefix, $config);
            return $connection;
        });

        Builder::macro('setCheckResult', function (bool $bool){
            $this->getConnection()->setCheckResult($bool);

            return $this;
        });

        $configList = config('rt_database.connections', []);
        $connections = $this->app['config']['database.connections'];
        foreach($configList as $name => $config) {
            $connections[$name] = $config;
        }
        $this->app['config']['database.connections'] = $connections;
    }
}
