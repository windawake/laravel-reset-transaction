<?php

namespace Laravel\ResetTransaction\Database;

use Illuminate\Database\MySqlConnection as DatabaseMySqlConnection;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\ResetTransaction\Facades\RT;

class MySqlConnection extends DatabaseMySqlConnection
{
    /**
     * The switch of detecting sql
     *
     * @var bool
     */
    protected $checkResult = false;

    /**
     * Detect the return value when committing the transaction
     *
     * @param bool $checkResult
     */
    public function setCheckResult(bool $checkResult)
    {
        $this->checkResult = $checkResult;
        return $this;
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Illuminate\Database\QueryException
     */
    protected function run($query, $bindings, Closure $callback)
    {
        $result = parent::run($query, $bindings, $callback);

        $rtTransactId = RT::getTransactId();
        if ($rtTransactId && $query && !strpos($query, 'reset_transaction')) {
            $action = strtolower(substr(trim($query), 0, 6));
            $sql = str_replace("?", "'%s'", $query);
            $completeSql = vsprintf($sql, $bindings);

            if (in_array($action, ['insert', 'update', 'delete'])) {
                $backupSql = $completeSql;
                if ($action == 'insert') {
                    $lastId = $this->getPdo()->lastInsertId();
                    // extract variables from sql
                    preg_match("/insert into (.+) \((.+)\) values \((.+)\)/", $backupSql, $match);
                    $database = $this->getConfig('database');
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

                $sqlItem = [
                    'transact_id' => RT::getTransactId(), 
                    'sql' => $backupSql, 
                    'result' => $result,
                    'check_result' => (int) $this->checkResult,
                ];
                session()->push('rt-transact_sql', $sqlItem);
            }

            // Log::info($completeSql);
        }

        $this->checkResult = false;

        return $result;
    }
}
