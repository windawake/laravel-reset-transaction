<?php

namespace Laravel\ResetTransaction\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Facades\RT;

class DistributeTransact
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $transactId = request()->header('transact_id');
        $connection = request()->header('transact_connection');
        if ($connection) {
            DB::setDefaultConnection($connection);
        }
        
        if ($transactId) {
            RT::middlewareBeginTransaction($transactId);
        }

        $response = $next($request);

        $transactId = request()->header('transact_id');
        if ($transactId) {
            RT::middlewareRollback();
        }

        return $response;
    }
}
