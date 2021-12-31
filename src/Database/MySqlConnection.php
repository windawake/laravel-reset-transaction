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

        RT::saveQuery($query, $bindings, $result, $this->checkResult);

        $this->checkResult = false;

        return $result;
    }
}
