<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlatformPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
                'data' => null,
                'errors' => ['auth' => ['No autenticado']],
            ], 401);
        }

        if (!$user->is_platform_admin || $user->tenant_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
                'data' => null,
                'errors' => ['platform_admin' => ['Solo un superadmin global puede acceder a esta ruta']],
            ], 403);
        }

        // El superadmin principal tiene acceso total por diseño
        if ($user->email === config('app.super_admin_email', 'admin@vende-mas.com.co')) {
            return $next($request);
        }

        if (!$user->hasAdminPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso de plataforma',
                'data' => null,
                'errors' => ['permission' => ["No tienes el permiso requerido: {$permission}"]],
            ], 403);
        }

        return $next($request);
    }
}
