<?php

namespace App\Http\Middleware;

use App\Services\TenantSchemaService;
use Closure;
use Illuminate\Http\Request;

class SetTenantSchema
{
    public function __construct(private readonly TenantSchemaService $tenantSchemaService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $this->tenantSchemaService->shouldUseSchemaMode() || ! $user || $user->is_platform_admin) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            return $next($request);
        }

        $this->tenantSchemaService->provision($tenant);
        $this->tenantSchemaService->setSearchPath($tenant);

        try {
            return $next($request);
        } finally {
            $this->tenantSchemaService->resetSearchPath();
        }
    }
}
