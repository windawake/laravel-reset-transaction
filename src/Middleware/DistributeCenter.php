<?php

namespace Laravel\ResetTransaction\Middleware;

use Closure;
use Illuminate\Support\Facades\Response;
use Laravel\ResetTransaction\Exception\RtException;
use Laravel\ResetTransaction\ExceptionCode;

class DistributeCenter
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
        $response = $next($request);

        if ($response->exception && $response->exception instanceof RtException) {
            $ret = [
                'error_code' => ExceptionCode::ERROR_RT,
                'message' => $response->exception->getMessage(),
                'errors' => []
            ];
            return Response::json($ret);
        }



        return $response;
    }
}
