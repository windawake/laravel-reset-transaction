<?php

namespace Laravel\ResetTransaction;

use Illuminate\Support\ServiceProvider;
use Laravel\ResetTransaction\Middleware\DistributeTransact;
use Laravel\ResetTransaction\Middleware\DistributeCenter;
use Laravel\ResetTransaction\Console\CreateExamples;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Laravel\ResetTransaction\Console\CleanRT;
use Laravel\ResetTransaction\Console\ReleaseRT;
use Laravel\ResetTransaction\Database\MySqlConnection;
use Laravel\ResetTransaction\Facades\ResetTransaction;
use Laravel\ResetTransaction\Facades\TransactionCenter;

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
        $this->app['router']->aliasMiddleware('distribute.center', DistributeCenter::class);

        $this->app->singleton(
            'command.resetTransact.create-examples',
            function ($app) {
                return new CreateExamples($app['files']);
            }
        );
        $this->app->singleton(
            'command.resetTransact.clean-rt',
            function ($app) {
                return new CleanRT();
            }
        );
        $this->app->singleton(
            'command.resetTransact.release-rt',
            function ($app) {
                return new ReleaseRT();
            }
        );
        $this->commands(
            'command.resetTransact.create-examples',
            'command.resetTransact.clean-rt',
            'command.resetTransact.release-rt'
        );

        $this->app->singleton('rt', function ($app) {
            return new ResetTransaction();
        });

        $this->app->singleton('rt_center', function ($app) {
            return new TransactionCenter();
        });



        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            // Next we can initialize the connection.
            $connection = new MySqlConnection($connection, $database, $prefix, $config);
            return $connection;
        });

        Builder::macro('setCheckResult', function (bool $bool) {
            $this->getConnection()->setCheckResult($bool);

            return $this;
        });

        $configList = config('rt_database.service_connections', []);
        $connections = $this->app['config']['database.connections'];
        foreach ($configList as $name => $config) {
            $connections[$name] = $config;
        }

        $centerConn = config('rt_database.center.connections.rt_center');
        if ($centerConn) {
            $connections['rt_center'] = $centerConn;
        }
        $this->app['config']['database.connections'] = $connections;
    }
}
