<?php

namespace Laravel\ResetTransaction\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Laravel\ResetTransaction\Exception\ResetTransactionException;
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
        $requestId = $request->header('rt_request_id');
        $transactId = $request->header('rt_transact_id');
        
        if ($transactId) {
            if (!$requestId) {
                throw new ResetTransactionException('rt_request_id cannot be null');
            }
            session()->put('rt_request_id', $requestId);
            $item = DB::connection('rt_center')->table('reset_transact_req')->where('request_id', $requestId)->first();
            if ($item) {
                $data = json_decode($item->response, true);
                return Response::json($data);
            }

            RT::middlewareBeginTransaction($transactId);
        }

        $response = $next($request);

        $requestId = $request->header('rt_request_id');
        $transactId = $request->header('rt_transact_id');
        $transactIdArr = explode('-', $transactId);
        if ($transactId && $response->isSuccessful()) {
            RT::middlewareRollback();
            DB::connection('rt_center')->table('reset_transact_req')->insert([
                'transact_id' => $transactIdArr[0],
                'request_id' => $requestId,
                'response' => $response->getContent(),
            ]);
        }

        return $response;
    }
}
