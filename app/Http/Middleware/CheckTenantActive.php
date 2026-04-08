<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckTenantActive
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->user()?->tenant;

        if (!$tenant || !$tenant->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta suspendida o vencida',
                'data' => null,
                'errors' => [
                    'tenant' => ['Cuenta suspendida o vencida'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
