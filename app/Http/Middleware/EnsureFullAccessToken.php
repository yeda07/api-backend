<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class EnsureFullAccessToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (
            !$user
            || $token instanceof TransientToken
            || $user->tokenCan('*')
            || $user->tokenCan('access:full')
        ) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Debes completar la autenticacion 2FA',
            'data' => null,
            'errors' => [
                'two_factor' => ['Acceso restringido hasta completar 2FA'],
            ],
        ], 403);
    }
}
