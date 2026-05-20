<?php

namespace App\Http\Middleware;

use App\Services\PlanPermissionService;
use Closure;
use Illuminate\Http\Request;

class CheckFeatureEnabled
{
    public function __construct(private readonly PlanPermissionService $planPermissionService)
    {
    }

    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        $user->loadMissing('tenant.plan');

        if ($user->is_platform_admin || ! $user->tenant?->plan) {
            return $next($request);
        }

        $featureKey = str_replace('-', '_', $feature);
        $features = $this->planPermissionService->featureFlagsForTenant($user->tenant);

        if (! ($features[$featureKey] ?? false)) {
            return response()->json([
                'message' => 'Este módulo no está disponible en tu plan',
            ], 403);
        }

        return $next($request);
    }
}
