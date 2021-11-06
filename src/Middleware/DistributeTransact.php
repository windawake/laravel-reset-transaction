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
        $transactId = request('transact_id');
        if ($transactId) {
            request()->merge(['stop_queryLog' => 1]);
            $sqlArr = DB::table('reset_transaction')->where('transact_id', $transactId)->pluck('sql')->toArray();
            $sql = implode(';', $sqlArr);
            DB::beginTransaction();
            if ($sqlArr) {
                DB::unprepared($sql);
            }
            request()->merge(['stop_queryLog' => 0]);
        }

        $response = $next($request);

        if ($transactId) {
            DB::rollBack();
            $sqlArr = request('transact_sql', []);
            foreach ($sqlArr as $sql) {
                $sql = value($sql);
                $action = substr($sql, 0, 6);
                DB::table('reset_transaction')->insert([
                    'transact_id' => $transactId,
                    'action' => $action,
                    'sql' => $sql,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $response = $next($request);

        return $response;
    }
}
