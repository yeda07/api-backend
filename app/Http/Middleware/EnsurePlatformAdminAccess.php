<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlatformAdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
                'data' => null,
                'meta' => null,
                'errors' => [
                    'auth' => ['No autenticado'],
                ],
            ], 401);
        }

        if (!$user->is_platform_admin || $user->tenant_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
                'data' => null,
                'meta' => null,
                'errors' => [
                    'platform_admin' => ['Solo un superadmin global puede acceder a esta ruta'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
