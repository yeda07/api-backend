<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlanPermissionService
{
    private const MODULE_PERMISSION_MAP = [
        'dashboard' => ['dashboard'],
        'inventario' => ['inventory', 'products', 'price-books'],
        'inventory' => ['inventory', 'products', 'price-books'],
        'ventas' => ['opportunities', 'quotations', 'products', 'finance', 'price-books'],
        'sales' => ['opportunities', 'quotations', 'products', 'finance', 'price-books'],
        'reportes' => ['reports', 'finance', 'inventory'],
        'reports' => ['reports', 'finance', 'inventory'],
        'crm' => ['accounts', 'contacts', 'relations', 'crm-entities', 'tags', 'search', 'tasks', 'interactions', 'activities', 'segments'],
        'proyectos' => ['projects'],
        'projects' => ['projects'],
        'rh-comisiones' => ['commissions'],
        'comisiones' => ['commissions'],
        'incentivos-comisiones' => ['commissions'],
        'incentives' => ['commissions'],
        'partners' => ['partners'],
        'canales-partners' => ['partners'],
        'inteligencia-competitiva' => ['competitive-intelligence'],
        'intelligence' => ['competitive-intelligence'],
        'automatizacion' => ['automation', 'segments'],
        'automation' => ['automation', 'segments'],
        'configuracion' => ['settings', 'users', 'custom-fields', 'teams', 'tags'],
        'settings' => ['settings', 'users', 'custom-fields', 'teams', 'tags'],
        'tareas' => ['tasks'],
        'tasks' => ['tasks'],
        'gastos' => ['expenses'],
        'expenses' => ['expenses'],
        'compras' => ['purchases'],
        'purchases' => ['purchases'],
    ];

    private const CORE_MODULES = [
        'dashboard',
        'settings',
        'users',
        'custom-fields',
        'teams',
        'logs',
        'metrics',
    ];

    public function allowedModulesForTenant(Tenant $tenant): ?array
    {
        $modules = data_get($tenant->plan?->features, 'modules', []);

        if (!is_array($modules) || $modules === []) {
            return null;
        }

        return collect($modules)
            ->flatMap(fn (string $module) => self::MODULE_PERMISSION_MAP[$this->normalizeModule($module)] ?? [$this->normalizeModule($module)])
            ->merge(self::CORE_MODULES)
            ->unique()
            ->values()
            ->all();
    }

    public function filterPermissionsForTenant(Collection $permissions, Tenant $tenant): Collection
    {
        $allowedModules = $this->allowedModulesForTenant($tenant);

        if ($allowedModules === null) {
            return $permissions;
        }

        return $permissions
            ->filter(fn (Permission $permission) => in_array($permission->module, $allowedModules, true))
            ->values();
    }

    public function assertPermissionsAllowedForTenant(Collection $permissions, Tenant $tenant): void
    {
        $allowedModules = $this->allowedModulesForTenant($tenant);

        if ($allowedModules === null) {
            return;
        }

        $denied = $permissions
            ->filter(fn (Permission $permission) => !in_array($permission->module, $allowedModules, true))
            ->pluck('key')
            ->values()
            ->all();

        if ($denied !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'permission_uids' => ['Permisos no incluidos en el plan activo: ' . implode(', ', $denied)],
            ]);
        }
    }

    private function normalizeModule(string $module): string
    {
        return Str::of($module)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();
    }
}
