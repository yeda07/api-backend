<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\SystemLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_plan_with_frontend_feature_payload(): void
    {
        $this->authenticateSuperadmin(['plans.manage']);

        $response = $this->postJson('/api/plans', [
            'name' => 'Plan Pro',
            'tier' => 'PRO',
            'price' => 49,
            'is_active' => true,
            'max_users' => 5,
            'storage_gb' => 10,
            'api_calls_month' => 10000,
            'modules' => ['Ventas', 'Inventario', 'RH / Comisiones'],
            'custom_domain' => true,
            'sso_saml' => false,
            'advanced_reports' => true,
            'support' => 'Email + Chat',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ACTIVO')
            ->assertJsonPath('data.features.storage_gb', 10)
            ->assertJsonPath('data.features.api_calls_month', 10000)
            ->assertJsonPath('data.features.support', 'Email + Chat');
    }

    public function test_plan_delete_deactivates_when_tenants_are_attached(): void
    {
        $this->authenticateSuperadmin(['plans.manage']);

        $plan = Plan::query()->create([
            'name' => 'Plan Starter',
            'price' => 49,
            'status' => 'ACTIVO',
        ]);

        Tenant::query()->create([
            'name' => 'Acme Corporation',
            'domain' => 'acme.test',
            'status' => 'ACTIVO',
            'plan_id' => $plan->getKey(),
            'is_active' => true,
        ]);

        $this->deleteJson('/api/plans/' . $plan->uid)
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVO');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->getKey(),
            'status' => 'INACTIVO',
        ]);
    }

    public function test_superadmin_can_export_billing_report(): void
    {
        $this->authenticateSuperadmin(['admin.billing.manage']);

        $tenant = $this->tenantWithPlan();

        Invoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'invoiceable_type' => Tenant::class,
            'invoiceable_id' => $tenant->getKey(),
            'invoice_number' => 'INV-001',
            'status' => 'overdue',
            'currency' => 'USD',
            'subtotal' => 100,
            'total' => 100,
            'outstanding_total' => 100,
            'issued_at' => '2026-03-01',
            'due_date' => '2026-03-15',
        ]);

        $this->getJson('/api/admin/billing/export?format=json')
            ->assertOk()
            ->assertJsonPath('data.summary.total_facturas', 1)
            ->assertJsonPath('data.summary.total_vencido', 100)
            ->assertJsonPath('data.rows.0.status', 'VENCIDA');
    }

    public function test_superadmin_can_read_billing_summary_cards(): void
    {
        $this->authenticateSuperadmin(['admin.billing.manage']);

        $tenant = $this->tenantWithPlan();

        Invoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'invoiceable_type' => Tenant::class,
            'invoiceable_id' => $tenant->getKey(),
            'invoice_number' => 'INV-PAID',
            'status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 100,
            'total' => 100,
            'paid_total' => 100,
            'outstanding_total' => 0,
            'issued_at' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        Invoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'invoiceable_type' => Tenant::class,
            'invoiceable_id' => $tenant->getKey(),
            'invoice_number' => 'INV-OVERDUE',
            'status' => 'overdue',
            'currency' => 'USD',
            'subtotal' => 80,
            'total' => 80,
            'outstanding_total' => 80,
            'issued_at' => now()->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->getJson('/api/admin/billing/summary')
            ->assertOk()
            ->assertJsonPath('data.cobrado_este_mes', 100)
            ->assertJsonPath('data.facturas_vencidas', 80)
            ->assertJsonPath('data.total_facturas', 2);
    }

    public function test_superadmin_tenant_index_uses_frontend_contract(): void
    {
        $this->authenticateSuperadmin(['admin.tenants.manage']);

        $tenant = $this->tenantWithPlan();
        $tenant->update([
            'api_calls_mes' => 123,
        ]);

        $this->getJson('/api/admin/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Acme Corporation')
            ->assertJsonPath('data.0.dominio', 'acme.test')
            ->assertJsonPath('data.0.plan_nombre', 'Plan Pro')
            ->assertJsonPath('data.0.api_calls_mes', 123)
            ->assertJsonPath('data.0.limite_api_calls', 10000);
    }

    public function test_superadmin_can_list_tenant_users_for_drawer(): void
    {
        $this->authenticateSuperadmin(['admin.tenants.manage']);

        $tenant = $this->tenantWithPlan();
        $role = \App\Models\Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Owner',
            'key' => 'owner',
            'is_system' => true,
        ]);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Juan Perez',
            'email' => 'juan@acme.com',
            'password' => bcrypt('secret123'),
            'last_login_at' => '2025-03-20 08:00:00',
        ]);
        $user->roles()->attach($role->getKey());

        $this->getJson('/api/admin/tenants/' . $tenant->uid . '/users')
            ->assertOk()
            ->assertJsonPath('data.0.uid', $user->uid)
            ->assertJsonPath('data.0.name', 'Juan Perez')
            ->assertJsonPath('data.0.email', 'juan@acme.com')
            ->assertJsonPath('data.0.rol', 'owner')
            ->assertJsonPath('data.0.ultimo_acceso', '2025-03-20T08:00:00.000000Z')
            ->assertJsonPath('data.0.estado', 'Activo')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 25)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_superadmin_can_paginate_tenant_users_for_drawer(): void
    {
        $this->authenticateSuperadmin(['admin.tenants.manage']);

        $tenant = $this->tenantWithPlan();

        foreach (['Ana Gomez', 'Juan Perez', 'Maria Ruiz'] as $index => $name) {
            User::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => $name,
                'email' => 'tenant-user-' . $index . '@acme.com',
                'password' => bcrypt('secret123'),
            ]);
        }

        $this->getJson('/api/admin/tenants/' . $tenant->uid . '/users?page=2&per_page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Maria Ruiz')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2);
    }

    public function test_tenant_user_creation_succeeds_when_reset_email_fails(): void
    {
        $this->authenticateSuperadmin(['admin.tenants.manage']);

        $tenant = $this->tenantWithPlan();

        app()->instance('auth.password', new class {
            public function sendResetLink(array $credentials, ?\Closure $callback = null): void
            {
                throw new \RuntimeException('mail transport unavailable');
            }
        });

        $this->postJson('/api/admin/tenants/' . $tenant->uid . '/users', [
            'name' => 'Nuevo Admin',
            'email' => 'nuevo-admin@acme.com',
            'role' => 'owner',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Nuevo Admin')
            ->assertJsonPath('data.email', 'nuevo-admin@acme.com')
            ->assertJsonPath('data.tenant_uid', $tenant->uid)
            ->assertJsonPath('data.reset_email_sent', false);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->getKey(),
            'email' => 'nuevo-admin@acme.com',
        ]);
    }

    public function test_tenant_user_creation_sends_password_reset_notification(): void
    {
        app()->forgetInstance('auth.password');
        Notification::fake();
        $this->authenticateSuperadmin(['admin.tenants.manage']);

        $tenant = $this->tenantWithPlan();

        $this->postJson('/api/admin/tenants/' . $tenant->uid . '/users', [
            'name' => 'Nuevo Owner',
            'email' => 'nuevo-owner@acme.com',
            'role' => 'owner',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reset_email_sent', true);

        $user = User::withoutGlobalScopes()
            ->where('email', 'nuevo-owner@acme.com')
            ->firstOrFail();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_superadmin_can_read_any_tenant_user_access(): void
    {
        $this->authenticateSuperadmin(['users.manage']);

        $tenant = $this->tenantWithPlan();
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->getJson('/api/users/' . $user->uid . '/access')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.uid', $user->uid);
    }

    public function test_superadmin_can_read_telemetry_summary(): void
    {
        $this->authenticateSuperadmin(['admin.telemetry.read']);

        $tenant = $this->tenantWithPlan();

        SystemLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'level' => 'critical',
            'message' => '503 Service Unavailable',
            'context' => ['latency_ms' => 240],
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        SystemLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'level' => 'info',
            'message' => 'Request',
            'context' => ['latency_ms' => 120],
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->getJson('/api/admin/telemetry/summary')
            ->assertOk()
            ->assertJsonPath('data.errors_24h', 1)
            ->assertJsonPath('data.tenants_with_errors', 1)
            ->assertJsonPath('data.latency_p95_ms', 240)
            ->assertJsonPath('data.errors_by_tenant.0.tenant_nombre', 'Acme Corporation');
    }

    private function authenticateSuperadmin(array $permissionKeys): User
    {
        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'admin',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $admin = User::withoutGlobalScopes()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.test',
            'password' => bcrypt('secret123'),
            'tenant_id' => null,
            'is_platform_admin' => true,
            'two_factor_secret' => 'secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $admin->permissions()->sync($permissionIds);

        Sanctum::actingAs($admin, ['access:full', 'platform:admin']);

        return $admin;
    }

    private function tenantWithPlan(): Tenant
    {
        $plan = Plan::query()->create([
            'name' => 'Plan Pro',
            'price' => 149,
            'status' => 'ACTIVO',
            'features' => [
                'api_calls_month' => 10000,
            ],
        ]);

        return Tenant::query()->create([
            'name' => 'Acme Corporation',
            'domain' => 'acme.test',
            'status' => 'ACTIVO',
            'plan_id' => $plan->getKey(),
            'mrr' => 149,
            'is_active' => true,
        ]);
    }
}
