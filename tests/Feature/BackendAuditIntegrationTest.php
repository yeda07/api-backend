<?php

namespace Tests\Feature;

use App\Models\InventoryProduct;
use App\Models\Partner;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackendAuditIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_and_roles_return_audit_fields(): void
    {
        $admin = $this->authenticateWithPermissions(['users.manage']);
        $role = Role::query()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Ventas',
            'key' => 'ventas',
        ]);
        $user = User::query()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Usuario Auditado',
            'email' => 'audit-user+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->roles()->attach($role->getKey());

        $this->getJson('/api/users?search=Usuario%20Auditado')
            ->assertOk()
            ->assertJsonPath('data.0.role_uid', $role->uid)
            ->assertJsonPath('data.0.role_name', 'Ventas')
            ->assertJsonPath('data.0.status', 'ACTIVO');

        $this->getJson('/api/rbac/roles')
            ->assertOk()
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.0.total_users', 1);
    }

    public function test_localization_is_readable_without_settings_permission_and_init_has_items(): void
    {
        $user = $this->authenticateWithPermissions(['dashboard.read']);

        $this->getJson('/api/settings/localization')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'UTC');

        $this->getJson('/api/auth/init')
            ->assertOk()
            ->assertJsonPath('data.user.is_platform_admin', false)
            ->assertJsonPath('data.modules.0.items.0.key', 'dashboard')
            ->assertJsonPath('data.modules.0.items.0.enabled', true);
    }

    public function test_tags_can_generate_key_and_return_empty_entity_types(): void
    {
        $this->authenticateWithPermissions(['tags.manage']);

        $this->postJson('/api/tags', [
            'name' => 'Cliente VIP',
            'color' => 'green',
        ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'cliente-vip')
            ->assertJsonPath('data.entity_types', []);

        Tag::query()->create([
            'name' => 'Sin Entidades',
            'key' => 'sin-entidades',
            'color' => '#111111',
            'entity_types' => null,
        ]);

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonPath('data.0.entity_types', []);
    }

    public function test_audited_500_endpoints_return_ok(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Audit 500',
            'status' => 'active',
            'is_active' => true,
        ]);
        $user = $this->tenantUser($tenant, ['finance.read']);
        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        Product::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Producto Pipeline',
            'type' => 'service',
            'sku' => 'PIPE-1',
            'status' => 'active',
        ]);
        InventoryProduct::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Inventario Pipeline',
            'sku' => 'INV-PIPE-1',
            'is_active' => true,
        ]);

        $this->getJson('/api/finance/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stats', 'weekly_sales', 'recent_invoices']]);

        $this->getJson('/api/tenant/opportunity-products')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Producto Pipeline', 'key' => 'PIPE-1'])
            ->assertJsonFragment(['name' => 'Inventario Pipeline', 'key' => 'INV-PIPE-1']);
    }

    public function test_pipeline_search_and_partner_stats_are_available(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Audit Search',
            'status' => 'active',
            'is_active' => true,
        ]);
        $user = $this->tenantUser($tenant, ['opportunities.read', 'partners.read']);
        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        Partner::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Distribuidor Norte',
            'type' => 'distributor',
            'status' => 'active',
        ]);

        $this->getJson('/api/opportunities/board?search=empresa')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stages']]);

        $this->getJson('/api/partners?with=stats')
            ->assertOk()
            ->assertJsonPath('data.stats.total_partners', 1)
            ->assertJsonPath('data.stats.active_partners', 1);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Audit',
            'status' => 'active',
            'is_active' => true,
        ]);

        $user = $this->tenantUser($tenant, $permissionKeys);
        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }

    private function tenantUser(Tenant $tenant, array $permissionKeys): User
    {
        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'audit',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Audit Owner',
            'email' => 'audit-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        return $user;
    }
}
