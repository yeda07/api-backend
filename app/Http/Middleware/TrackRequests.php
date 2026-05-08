<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\LoggerService;
use Illuminate\Http\Request;

class TrackRequests
{
    public function handle(Request $request, Closure $next)
    {
        $startedAt = microtime(true);

        $response = $next($request);

        if ($this->shouldLog($request)) {
            LoggerService::log('info', 'API request', [
                'method' => $request->method(),
                'path' => '/' . ltrim($request->path(), '/'),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'user_uid' => $request->user()?->uid,
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if (!filter_var(env('LOG_DB_REQUESTS', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (!$request->is('api/*')) {
            return false;
        }

        return !$request->is('api/logs') && !$request->is('api/admin/telemetry/logs');
    }
}
