<?php

namespace Laravel\ResetTransaction\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

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
        if ($transactId) {
            $sqlArr = DB::table('reset_transaction')->where('transact_id', $transactId)->pluck('sql')->toArray();
            $sql = implode(';', $sqlArr);
            DB::beginTransaction();
            if ($sqlArr) {
                DB::unprepared($sql);
            }

            session()->put('transact_id', $transactId);
        }

        $response = $next($request);

        if ($transactId) {
            DB::rollBack();
            session()->remove('transact_id');
            $sqlArr = session()->remove('transact_sql');

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
