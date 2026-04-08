<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
                'data' => null,
                'errors' => [
                    'auth' => ['No autenticado'],
                ],
            ], 401);
        }

        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
                'data' => null,
                'errors' => [
                    'permission' => ["No tienes el permiso requerido: {$permission}"],
                ],
            ], 403);
        }

        return $next($request);
    }
}
