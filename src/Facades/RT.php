<?php

namespace Laravel\ResetTransaction\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 * @method static void setTransactId(string $transactId)
 * @method static string getTransactId()
 * @method static void middlewareRollback()
 * @method static void middlewareBeginTransaction(string $transactId)
 * @method static void saveQuery(string $query, array $bindings, int $result, bool $checkResult, string $keyName = null, int $id = null)
 * 
 * @see \Laravel\ResetTransaction\Facades\ResetTransaction
 *
 */
class RT extends Facade
{
    const STATUS_START = 0;
    const STATUS_COMMIT = 1;
    const STATUS_ROLLBACK = 2;

    const ACTION_CREATE = 0;
    const ACTION_WAIT_COMMIT = 10;
    const ACTION_PREPARE_COMMIT = 11;
    const ACTION_FINISH_COMMIT = 12;
    const ACTION_WAIT_ROLLBACK = 20;
    const ACTION_PREPARE_ROLLBACK = 21;
    const ACTION_FINISH_ROLLBACK = 22;
    
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'rt';
    }
}
