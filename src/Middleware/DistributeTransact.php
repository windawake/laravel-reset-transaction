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
            $txIdArr = explode('-', $transactId);
            $sqlArr = DB::table('reset_transaction')->where('transact_id', 'like', $txIdArr[0].'%')->pluck('sql')->toArray();
            $sql = implode(';', $sqlArr);
            RT::beginTransaction($transactId);
            if ($sqlArr) {
                DB::unprepared($sql);
            }

        }

        $response = $next($request);

        $transactId = request()->header('transact_id');
        if ($transactId) {
            DB::rollBack();
            $sqlArr = session()->remove('rt-transact_sql');

            if ($sqlArr) {
                foreach ($sqlArr as $item) {
                    DB::table('reset_transaction')->insert([
                        'transact_id' => $transactId,
                        'sql' => value($item['sql']),
                        'result' => $item['result'],
                    ]);
                }
            }
        }

        return $response;
    }
}
