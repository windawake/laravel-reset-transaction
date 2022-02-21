<?php

namespace Laravel\ResetTransaction\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string beginTransaction()
 * @method static mixed commit()
 * @method static mixed rollBack()
 * @method static void setTransactId(string $transactId)
 * @method static string getTransactId()
 * @method static void middlewareRollback()
 * @method static void middlewareBeginTransaction(string $transactId)
 * @method static mixed centerCommit(string $transactId, array $transactRollback)
 * @method static mixed centerRollback(string $transactId, array $transactRollback)
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
