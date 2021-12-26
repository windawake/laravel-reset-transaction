<?php

namespace Laravel\ResetTransaction\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 *
 */
class RT extends Facade
{
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
