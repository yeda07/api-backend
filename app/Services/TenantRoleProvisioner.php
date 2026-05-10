<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantRoleProvisioner
{
    public function provision(Tenant $tenant): void
    {
        foreach ($this->roleDefinitions() as $key => $definition) {
            $role = Role::withoutGlobalScopes()->firstOrNew([
                'tenant_id' => $tenant->getKey(),
                'key' => $key,
            ]);

            $role->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_system' => true,
            ]);

            if (empty($role->uid)) {
                $role->uid = (string) Str::uuid();
            }

            $role->save();

            $permissionIds = Permission::query()
                ->whereIn('key', $definition['permissions'])
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }

    private function roleDefinitions(): array
    {
        return [
            'owner' => [
                'name' => 'Owner',
                'description' => 'Acceso total al tenant',
                'permissions' => Permission::query()
                    ->where('module', '!=', 'admin')
                    ->where('key', '!=', 'plans.manage')
                    ->pluck('key')
                    ->all(),
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Gestion comercial y operativa del tenant',
                'permissions' => [
                    'accounts.read',
                    'accounts.create',
                    'accounts.update',
                    'accounts.delete',
                    'contacts.read',
                    'contacts.create',
                    'contacts.update',
                    'contacts.delete',
                    'relations.read',
                    'relations.create',
                    'relations.delete',
                    'crm-entities.read',
                    'crm-entities.create',
                    'crm-entities.update',
                    'tags.manage',
                    'search.use',
                    'dashboard.read',
                    'tasks.read',
                    'tasks.create',
                    'tasks.update',
                    'tasks.delete',
                    'interactions.read',
                    'interactions.create',
                    'activities.read',
                    'activities.create',
                    'activities.update',
                    'activities.delete',
                    'documents.read',
                    'documents.create',
                    'documents.manage',
                    'inventory.read',
                    'inventory.manage',
                    'inventory.reserve',
                    'inventory.report',
                    'quotations.read',
                    'quotations.create',
                    'quotations.update',
                    'products.read',
                    'products.manage',
                    'products.install',
                    'price-books.read',
                    'price-books.manage',
                    'commissions.read',
                    'commissions.manage',
                    'opportunities.read',
                    'opportunities.manage',
                    'finance.read',
                    'finance.manage',
                    'reports.read',
                    'custom-fields.manage',
                    'settings.manage',
                    'logs.read',
                    'metrics.read',
                    'expenses.read',
                    'expenses.manage',
                    'expenses.report',
                    'purchases.read',
                    'purchases.manage',
                    'competitive-intelligence.read',
                    'competitive-intelligence.manage',
                    'competitive-intelligence.report',
                    'partners.read',
                    'partners.manage',
                    'partners.opportunities.read',
                    'partners.opportunities.manage',
                    'partners.resources.read',
                    'partners.resources.manage',
                    'projects.read',
                    'projects.manage',
                ],
            ],
            'seller' => [
                'name' => 'Seller',
                'description' => 'Operacion comercial diaria sobre su cartera',
                'permissions' => [
                    'accounts.read',
                    'accounts.create',
                    'accounts.update',
                    'contacts.read',
                    'contacts.create',
                    'contacts.update',
                    'relations.read',
                    'relations.create',
                    'crm-entities.read',
                    'crm-entities.create',
                    'crm-entities.update',
                    'tags.manage',
                    'search.use',
                    'tasks.read',
                    'tasks.create',
                    'tasks.update',
                    'interactions.read',
                    'interactions.create',
                    'activities.read',
                    'activities.create',
                    'activities.update',
                    'documents.read',
                    'documents.create',
                    'documents.manage',
                    'inventory.read',
                    'inventory.reserve',
                    'inventory.report',
                    'quotations.read',
                    'quotations.create',
                    'quotations.update',
                    'products.read',
                    'products.install',
                    'price-books.read',
                    'commissions.read',
                    'opportunities.read',
                    'opportunities.manage',
                    'finance.read',
                    'reports.read',
                    'custom-fields.manage',
                    'settings.manage',
                    'metrics.read',
                    'expenses.read',
                    'expenses.report',
                    'purchases.read',
                    'competitive-intelligence.read',
                    'competitive-intelligence.manage',
                    'partners.read',
                    'partners.opportunities.read',
                    'partners.opportunities.manage',
                    'partners.resources.read',
                    'projects.read',
                ],
            ],
        ];
    }
}
