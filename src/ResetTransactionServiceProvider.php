<?php

namespace Laravel\ResetTransaction;

use Illuminate\Support\ServiceProvider;
use Laravel\ResetTransaction\Middleware\DistributeTransact;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\ResetTransaction\Console\CreateExamples;

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

        Event::listen(QueryExecuted::class, function ($event) {
            $stopQueryLog = request('stop_queryLog');
            $transactId = request('transact_id');
            if (!$stopQueryLog && $event->sql && $transactId) {
                $action = strtolower(substr(trim($event->sql), 0, 6));
                $sql = str_replace("?", "'%s'", $event->sql);
                $completeSql = vsprintf($sql, $event->bindings);
                if (in_array($action, ['insert', 'update', 'delete']) && !strpos($event->sql, 'reset_transaction')) {
                    $backupSql = $completeSql;
                    if ($action == 'insert') {
                        $lastId = $event->connection->getPdo()->lastInsertId();
                        // extract variables from sql
                        preg_match("/insert into (.+) \((.+)\) values \((.+)\)/", $backupSql, $match);
                        $database = $event->connection->getConfig('database');
                        $table = $match[1];
                        $columns = $match[2];
                        $parameters = $match[3];

                        $backupSql = function () use ($database, $table, $columns, $parameters, $lastId) {
                            $columnItem = DB::selectOne('select column_name as `column_name` from information_schema.columns where table_schema = ? and table_name = ? and column_key="PRI"', [$database, trim($table, '`')]);
                            $primaryKey = $columnItem->column_name;

                            $columns = "`{$primaryKey}`, " . $columns;

                            $parameters = "'{$lastId}', " . $parameters;
                            return "insert into $table ($columns) values ($parameters)";
                        };
                    }

                    $arr = request('transact_sql', []);
                    $arr[] = $backupSql;

                    request()->merge(['transact_sql' => $arr]);
                }

                Log::info($completeSql);
            }
        });
    }
}
