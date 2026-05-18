<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Currency;
use App\Models\InventoryProduct;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformGeneralIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_init_returns_user_tenant_modules_and_localization(): void
    {
        $currency = Currency::query()->create([
            'code' => 'ARS',
            'name' => 'Peso argentino',
            'symbol' => '$',
        ]);
        $plan = Plan::query()->create([
            'name' => 'Pro',
            'tier' => 'pro',
            'price' => 99,
            'status' => 'active',
        ]);
        $tenant = Tenant::query()->create([
            'name' => 'Empresa Demo',
            'status' => 'active',
            'is_active' => true,
            'plan_id' => $plan->getKey(),
            'currency_id' => $currency->getKey(),
        ]);
        $tenant->forceFill([
            'timezone' => 'America/Argentina/Buenos_Aires',
            'date_format' => 'd/m/Y',
        ])->save();

        $user = $this->tenantUser($tenant, [
            'dashboard.read',
            'inventory.read',
            'inventory.manage',
            'users.manage',
        ]);
        $user->forceFill(['timezone' => 'America/Bogota'])->save();
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Admin',
            'key' => 'admin',
        ]);
        $user->roles()->attach($role->getKey());

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        $this->getJson('/api/auth/init')
            ->assertOk()
            ->assertJsonPath('data.user.uid', $user->uid)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.tenant.uid', $tenant->uid)
            ->assertJsonPath('data.tenant.plan', 'pro')
            ->assertJsonPath('data.localization.currency', 'ARS')
            ->assertJsonPath('data.localization.currency_symbol', '$')
            ->assertJsonPath('data.localization.locale', 'es-AR')
            ->assertJsonPath('data.localization.timezone', 'America/Argentina/Buenos_Aires')
            ->assertJsonPath('data.localization.date_format', 'DD/MM/YYYY')
            ->assertJsonPath('data.localization.language', 'es')
            ->assertJsonPath('data.modules.0.key', 'dashboard')
            ->assertJsonPath('data.modules.0.enabled', true)
            ->assertJsonPath('data.modules.1.key', 'inventory')
            ->assertJsonPath('data.modules.1.enabled', true)
            ->assertJsonPath('data.modules.1.permissions.0', 'read')
            ->assertJsonPath('data.modules.1.permissions.1', 'manage')
            ->assertJsonPath('data.modules.10.key', 'settings')
            ->assertJsonPath('data.modules.10.enabled', true);
    }

    public function test_auth_init_disables_tenant_modules_for_platform_admin_without_removing_permissions(): void
    {
        foreach (['users.manage', 'admin.dashboard.read'] as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'platform',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $admin = User::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'platform-admin+'.uniqid().'@example.test',
            'password' => bcrypt('secret123'),
            'is_platform_admin' => true,
        ]);
        $admin->permissions()->sync(Permission::query()->whereIn('key', ['users.manage', 'admin.dashboard.read'])->pluck('id')->all());

        Sanctum::actingAs($admin, ['access:full', 'platform:admin']);

        $response = $this->getJson('/api/auth/init')
            ->assertOk()
            ->assertJsonPath('data.user.is_platform_admin', true)
            ->assertJsonPath('data.modules.0.enabled', false)
            ->assertJsonPath('data.modules.0.items.0.enabled', false)
            ->assertJsonPath('data.modules.10.key', 'settings')
            ->assertJsonPath('data.modules.10.enabled', false)
            ->assertJsonPath('data.modules.10.permissions', []);

        $this->assertContains('users.manage', $response->json('data.permissions.effective'));
        $this->assertContains('admin.dashboard.read', $response->json('data.permissions.effective'));
    }

    public function test_auth_init_includes_sales_catalog_item(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Catalog',
            'status' => 'active',
            'is_active' => true,
        ]);
        $user = $this->tenantUser($tenant, [
            'products.read',
            'products.manage',
        ]);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        $response = $this->getJson('/api/auth/init')->assertOk();
        $sales = collect($response->json('data.modules'))->firstWhere('key', 'sales');
        $catalog = collect($sales['items'])->firstWhere('key', 'catalog');

        $this->assertSame('Catalogo Comercial', $catalog['label']);
        $this->assertTrue($catalog['enabled']);
        $this->assertSame(['read', 'manage'], $catalog['permissions']);
    }

    public function test_localization_endpoint_returns_frontend_fields(): void
    {
        $currency = Currency::query()->create([
            'code' => 'COP',
            'name' => 'Peso colombiano',
            'symbol' => '$',
        ]);
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Localization',
            'status' => 'active',
            'is_active' => true,
            'currency_id' => $currency->getKey(),
        ]);
        $tenant->forceFill(['timezone' => 'America/Bogota', 'date_format' => 'd/m/Y'])->save();
        $user = $this->tenantUser($tenant, ['settings.manage']);
        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        $this->getJson('/api/settings/localization')
            ->assertOk()
            ->assertJsonPath('data.currency', 'COP')
            ->assertJsonPath('data.currency_symbol', '$')
            ->assertJsonPath('data.locale', 'es-CO')
            ->assertJsonPath('data.language', 'es')
            ->assertJsonPath('data.date_format', 'DD/MM/YYYY');
    }

    public function test_tenant_dynamic_option_endpoints_are_available(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Options',
            'status' => 'active',
            'is_active' => true,
        ]);
        $user = $this->tenantUser($tenant, []);
        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        Account::query()->create([
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $user->getKey(),
            'name' => 'Cuenta Salud',
            'document' => 'SALUD-1',
            'industry' => 'Salud',
        ]);
        Product::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'CRM Enterprise',
            'type' => 'service',
            'sku' => 'CRM-ENT',
            'status' => 'active',
        ]);
        InventoryProduct::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Licencia Base',
            'sku' => 'LIC-BASE',
            'is_active' => true,
        ]);

        foreach ([
            '/api/tenant/payment-methods',
            '/api/tenant/lead-origins',
            '/api/tenant/institution-types',
            '/api/tenant/company-sizes',
            '/api/tenant/lost-reason-categories',
            '/api/tenant/activity-types',
            '/api/tenant/commission-plan-types',
        ] as $endpoint) {
            $this->getJson($endpoint)
                ->assertOk()
                ->assertJsonStructure(['data' => [['uid', 'name', 'key']]]);
        }

        $this->getJson('/api/tenant/industries')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Salud', 'key' => 'salud']);

        $this->getJson('/api/tenant/opportunity-products')
            ->assertOk()
            ->assertJsonFragment(['name' => 'CRM Enterprise', 'key' => 'CRM-ENT'])
            ->assertJsonFragment(['name' => 'Licencia Base', 'key' => 'LIC-BASE']);
    }

    public function test_rbac_roles_can_filter_permissions_by_active_plan_modules(): void
    {
        $inventoryPermission = Permission::query()->create([
            'key' => 'inventory.read',
            'module' => 'inventory',
            'action' => 'read',
            'description' => 'inventory.read',
        ]);
        $salesPermission = Permission::query()->create([
            'key' => 'opportunities.read',
            'module' => 'opportunities',
            'action' => 'read',
            'description' => 'opportunities.read',
        ]);
        Permission::query()->create([
            'key' => 'users.manage',
            'module' => 'users',
            'action' => 'manage',
            'description' => 'users.manage',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Inventory Only',
            'price' => 49,
            'status' => 'active',
            'features' => [
                'modules' => ['inventario'],
            ],
        ]);
        $tenant = Tenant::query()->create([
            'name' => 'Tenant RBAC',
            'status' => 'active',
            'is_active' => true,
            'plan_id' => $plan->getKey(),
        ]);
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Operador',
            'key' => 'operator',
        ]);
        $role->permissions()->sync([$inventoryPermission->getKey(), $salesPermission->getKey()]);

        $user = $this->tenantUser($tenant, ['users.manage']);
        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        $this->getJson('/api/rbac/roles?only_active_modules=true')
            ->assertOk()
            ->assertJsonPath('data.0.permissions.0.key', 'inventory.read')
            ->assertJsonMissing(['key' => 'opportunities.read']);
    }

    private function tenantUser(Tenant $tenant, array $permissionKeys): User
    {
        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'platform',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Platform Owner',
            'email' => 'platform-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        if ($permissionKeys !== []) {
            $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
            $user->permissions()->sync($permissionIds);
        }

        return $user;
    }
}
