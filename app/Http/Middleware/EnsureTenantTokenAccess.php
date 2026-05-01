<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class EnsureTenantTokenAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (!$user) {
            return $next($request);
        }

        if ($token instanceof TransientToken) {
            return $next($request);
        }

        if ($user->is_platform_admin && $user->tenant_id === null) {
            if ($user->tokenCan('*') || $user->tokenCan('platform:admin')) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Token invalido para plataforma',
                'data' => null,
                'errors' => [
                    'token' => ['El token no pertenece a un superadmin global valido'],
                ],
            ], 403);
        }

        $tenantAbility = 'tenant:' . $user->tenant?->uid;

        if ($user->tokenCan('*') || $user->tokenCan($tenantAbility)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Token invalido para el tenant actual',
            'data' => null,
            'errors' => [
                'token' => ['El token no pertenece al tenant autenticado'],
            ],
        ], 403);
    }
}
