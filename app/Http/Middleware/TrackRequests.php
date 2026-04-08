<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\LoggerService;

class TrackRequests
{
    public function handle($request, Closure $next)
    {
        LoggerService::log('info', 'Request', [
            'url' => $request->fullUrl(),
            'method' => $request->method()
        ]);

        return $next($request);
    }
}
