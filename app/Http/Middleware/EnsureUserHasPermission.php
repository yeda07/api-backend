<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Services\PlanPermissionService;
use Closure;
use Illuminate\Http\Request;

class EnsureUserHasPermission
{
    public function __construct(private readonly PlanPermissionService $planPermissionService)
    {
    }

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
            if (!$user->is_platform_admin || !$user->hasAdminPermissionTo($permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                    'data' => null,
                    'errors' => [
                        'permission' => ["No tienes el permiso requerido: {$permission}"],
                    ],
                ], 403);
            }
        }

        if (! $user->is_platform_admin && $user->tenant) {
            $requiredPermission = Permission::query()->where('key', $permission)->first();
            $allowedModules = $this->planPermissionService->allowedModulesForTenant($user->tenant);

            if ($requiredPermission && $allowedModules !== null && ! in_array($requiredPermission->module, $allowedModules, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado por el plan activo',
                    'data' => null,
                    'errors' => [
                        'permission' => ["El permiso {$permission} no esta incluido en el plan activo"],
                    ],
                ], 403);
            }
        }

        return $next($request);
    }
}
