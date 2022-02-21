<?php

namespace Laravel\ResetTransaction\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed commit(string $transactId, array $transactRollback)
 * @method static mixed rollback(string $transactId, array $transactRollback)
 *
 * @see \Laravel\ResetTransaction\Facades\TransactionCenter
 *
 */
class RTCenter extends Facade
{
    const ACTION_START = 0;
    const ACTION_END = 1;
    const ACTION_PREPARE = 2;
    const ACTION_PREPARE_COMMIT = 3;
    const ACTION_PREPARE_ROLLBACK = 4;
    const ACTION_COMMIT = 5;
    const ACTION_ROLLBACK = 6;
    
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'rt_center';
    }
}
