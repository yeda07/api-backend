<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class PlatformInitService
{
    private const MODULES = [
        'dashboard' => ['label' => 'Dashboard', 'permissions' => ['dashboard.read']],
        'inventory' => ['label' => 'Inventario', 'permissions' => ['inventory.read', 'inventory.manage', 'inventory.reserve', 'inventory.report']],
        'sales' => ['label' => 'Ventas', 'permissions' => ['opportunities.read', 'opportunities.manage', 'quotes.read', 'quotes.manage', 'quotations.read', 'quotations.manage', 'products.read', 'products.manage']],
        'reports' => ['label' => 'Reportes', 'permissions' => ['finance.read', 'inventory.report']],
        'crm' => ['label' => 'CRM', 'permissions' => ['accounts.read', 'accounts.create', 'accounts.update', 'accounts.delete', 'contacts.read', 'contacts.create', 'contacts.update', 'contacts.delete', 'crm-entities.read', 'crm-entities.create', 'crm-entities.update']],
        'projects' => ['label' => 'Proyectos', 'permissions' => ['projects.read', 'projects.manage']],
        'incentives' => ['label' => 'Incentivos y Comisiones', 'permissions' => ['commissions.read', 'commissions.manage']],
        'partners' => ['label' => 'Canales & Partners', 'permissions' => ['partners.read', 'partners.manage', 'partners.opportunities.read', 'partners.opportunities.manage', 'partners.resources.read', 'partners.resources.manage']],
        'intelligence' => ['label' => 'Inteligencia Competitiva', 'permissions' => ['competitive-intelligence.read', 'competitive-intelligence.manage', 'competitive-intelligence.report']],
        'automation' => ['label' => 'Automatizacion', 'permissions' => ['automation.read', 'automation.create', 'automation.update', 'automation.delete', 'segments.read', 'segments.manage']],
        'settings' => ['label' => 'Configuracion', 'permissions' => ['users.manage', 'custom-fields.manage']],
    ];

    public function init(User $user): array
    {
        $user->loadMissing(['tenant.plan', 'tenant.currency', 'roles', 'permissions']);
        $effectivePermissionKeys = $user->effectivePermissions()->pluck('key')->values()->all();
        $role = $user->roles->first()?->key ?? ($user->is_platform_admin ? 'platform-admin' : 'user');

        return [
            'user' => [
                'uid' => $user->uid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'avatar_url' => null,
            ],
            'tenant' => [
                'uid' => $user->tenant?->uid,
                'name' => $user->tenant?->name,
                'plan' => $user->tenant?->plan?->tier ?? $user->tenant?->plan?->name ?? 'free',
                'logo_url' => null,
            ],
            'modules' => $this->modules($effectivePermissionKeys),
            'localization' => $this->localization($user),
        ];
    }

    public function localization(User $user): array
    {
        $tenant = $user->tenant;
        $currency = $tenant?->currency;
        $currencyCode = $currency?->code ?? 'USD';

        return [
            'currency' => $currencyCode,
            'currency_symbol' => $currency?->symbol ?? '$',
            'locale' => $this->localeFor($currencyCode),
            'timezone' => $tenant?->timezone ?? 'UTC',
            'date_format' => $this->frontendDateFormat($tenant?->date_format ?? 'Y-m-d'),
            'language' => 'es',
            'user_timezone' => $user->timezone ?? null,
        ];
    }

    private function modules(array $effectivePermissionKeys): array
    {
        return collect(self::MODULES)
            ->map(function (array $module, string $key) use ($effectivePermissionKeys) {
                $actions = collect($module['permissions'])
                    ->filter(fn (string $permission) => in_array($permission, $effectivePermissionKeys, true))
                    ->map(fn (string $permission) => str($permission)->afterLast('.')->toString())
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'key' => $key,
                    'label' => $module['label'],
                    'enabled' => !empty($actions),
                    'permissions' => $actions,
                ];
            })
            ->values()
            ->all();
    }

    private function localeFor(string $currencyCode): string
    {
        return match ($currencyCode) {
            'ARS' => 'es-AR',
            'COP' => 'es-CO',
            'MXN' => 'es-MX',
            default => 'es-ES',
        };
    }

    private function frontendDateFormat(string $format): string
    {
        return match ($format) {
            'Y-m-d' => 'YYYY-MM-DD',
            'd/m/Y' => 'DD/MM/YYYY',
            'm/d/Y' => 'MM/DD/YYYY',
            default => $format,
        };
    }
}
