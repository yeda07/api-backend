<?php

namespace Tests\Feature;

use App\Models\InventoryProduct;
use App\Models\Account;
use App\Models\CommissionEntry;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
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

        $account = Account::query()->create([
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $user->getKey(),
            'name' => 'Empresa Buscada',
            'document' => 'AUDIT-SEARCH-' . uniqid(),
        ]);
        $stage = OpportunityStage::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Leads',
            'key' => 'leads-audit',
            'position' => 1,
            'is_active' => true,
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $user->getKey(),
            'stage_id' => $stage->getKey(),
            'opportunityable_type' => Account::class,
            'opportunityable_id' => $account->getKey(),
            'title' => 'Renovacion anual',
            'amount' => 1000,
        ]);

        Partner::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Distribuidor Norte',
            'type' => 'distributor',
            'status' => 'active',
        ]);

        $this->getJson('/api/opportunities/board?search=Buscada')
            ->assertOk()
            ->assertJsonPath('data.stages.0.items.0.uid', $opportunity->uid);

        $this->getJson('/api/partners?with=stats')
            ->assertOk()
            ->assertJsonPath('data.stats.total_partners', 1)
            ->assertJsonPath('data.stats.active_partners', 1);
    }

    public function test_commission_history_pdf_and_paginated_audit_endpoints_are_available(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Audit Pagination',
            'status' => 'active',
            'is_active' => true,
        ]);
        $user = $this->tenantUser($tenant, [
            'commissions.read',
            'partners.read',
            'partners.opportunities.read',
            'partners.resources.read',
            'opportunities.read',
            'inventory.read',
            'purchases.read',
            'segments.read',
            'automation.read',
            'custom-fields.manage',
            'finance.read',
            'expenses.read',
            'relations.read',
        ]);
        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        CommissionEntry::query()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'base_amount' => 1000,
            'rate_percent' => 10,
            'commission_amount' => 100,
            'status' => 'earned',
            'earned_at' => now()->toDateString(),
        ]);

        $this->get('/api/commissions/history/pdf?period=' . now()->format('Y-m'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        foreach ([
            '/api/commissions/entries?per_page=1',
            '/api/commissions/runs?per_page=1',
            '/api/commissions/plans?per_page=1',
            '/api/commissions/rules?per_page=1',
            '/api/commissions/targets?per_page=1',
            '/api/partners?per_page=1',
            '/api/partners/opportunities?per_page=1',
            '/api/partner-resources?per_page=1',
            '/api/inventory/movements?per_page=1',
            '/api/purchases/payables?per_page=1',
            '/api/segments?per_page=1',
            '/api/automation/rules?per_page=1',
            '/api/automation/assignment-rules?per_page=1',
            '/api/currency/rates?per_page=1',
            '/api/custom-fields?per_page=1',
            '/api/expenses/suppliers?per_page=1',
            '/api/relations?per_page=1',
            '/api/relations/with-entities?per_page=1',
        ] as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertOk();

            $this->assertIsArray($response->json('meta.pagination'), 'Missing pagination meta for ' . $endpoint);
        }
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
