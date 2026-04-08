<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roleDefinitions = [
            'owner' => [
                'name' => 'Owner',
                'description' => 'Acceso total al tenant',
                'permissions' => Permission::query()->pluck('key')->all(),
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
                    'inventory.read',
                    'inventory.manage',
                    'inventory.reserve',
                    'inventory.report',
                    'quotations.read',
                    'quotations.create',
                    'quotations.update',
                    'price-books.read',
                    'price-books.manage',
                    'commissions.read',
                    'commissions.manage',
                    'custom-fields.manage',
                    'logs.read',
                    'metrics.read',
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
                    'inventory.read',
                    'inventory.reserve',
                    'inventory.report',
                    'quotations.read',
                    'quotations.create',
                    'quotations.update',
                    'price-books.read',
                    'commissions.read',
                    'custom-fields.manage',
                    'metrics.read',
                ],
            ],
        ];

        foreach (Tenant::query()->get() as $tenant) {
            foreach ($roleDefinitions as $key => $definition) {
                $role = Role::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->getKey(),
                        'key' => $key,
                    ],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'is_system' => true,
                    ]
                );

                $permissionIds = Permission::query()
                    ->whereIn('key', $definition['permissions'])
                    ->pluck('id')
                    ->all();

                $role->permissions()->sync($permissionIds);
            }
        }
    }
}
