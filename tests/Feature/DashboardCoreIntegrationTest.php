<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Activity;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardCoreIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_core_returns_frontend_integration_blocks(): void
    {
        Cache::flush();

        $tenant = Tenant::query()->create([
            'name' => 'Acme Corporation',
            'is_active' => true,
        ]);
        $user = $this->authenticateTenantUser($tenant, ['dashboard.read'], true);

        $account = Account::query()->create([
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $user->getKey(),
            'name' => 'TechMex Solutions',
            'document' => '900123456',
        ]);

        $riskTag = Tag::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Riesgo',
            'key' => 'risk',
            'color' => '#ef4444',
            'category' => 'risk',
        ]);
        $account->tags()->attach($riskTag->getKey());

        $task = Task::query()->create([
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $user->getKey(),
            'assigned_user_id' => $user->getKey(),
            'title' => 'Enviar propuesta comercial',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => today(),
            'taskable_type' => Account::class,
            'taskable_id' => $account->getKey(),
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $user->getKey(),
            'created_by_user_id' => $user->getKey(),
            'quoteable_type' => Account::class,
            'quoteable_id' => $account->getKey(),
            'quote_number' => 'COT-2026-0001',
            'title' => 'Renovacion',
            'status' => 'sent',
            'currency' => 'USD',
        ]);
        QuotationItem::query()->create([
            'tenant_id' => $tenant->getKey(),
            'quotation_id' => $quotation->getKey(),
            'description' => 'Servicio mensual',
            'quantity' => 2,
            'list_unit_price' => 100,
            'net_unit_price' => 100,
            'unit_price' => 100,
        ]);

        Invoice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'invoiceable_type' => Account::class,
            'invoiceable_id' => $account->getKey(),
            'invoice_number' => 'INV-001',
            'status' => 'issued',
            'currency' => 'USD',
            'subtotal' => 200,
            'total' => 200,
            'outstanding_total' => 200,
            'issued_at' => today(),
        ]);

        $warehouse = Warehouse::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Principal',
            'code' => 'MAIN',
            'is_active' => true,
        ]);
        $product = InventoryProduct::query()->create([
            'tenant_id' => $tenant->getKey(),
            'sku' => 'SKU-001-XL',
            'name' => 'Camiseta Basica XL',
            'reorder_point' => 25,
            'is_active' => true,
        ]);
        InventoryStock::query()->create([
            'tenant_id' => $tenant->getKey(),
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'physical_stock' => 3,
            'reserved_stock' => 0,
        ]);

        $this->getJson('/api/dashboard/core')
            ->assertOk()
            ->assertJsonPath('data.kpis.mrr', 200)
            ->assertJsonPath('data.kpis.at_risk_count', 1)
            ->assertJsonPath('data.overdue_tasks.0.uid', $task->uid)
            ->assertJsonPath('data.overdue_tasks.0.account_name', 'TechMex Solutions')
            ->assertJsonPath('data.overdue_tasks.0.assigned_to_name', $user->name)
            ->assertJsonPath('data.recent_quotations.0.number', 'COT-2026-0001')
            ->assertJsonPath('data.recent_quotations.0.account_name', 'TechMex Solutions')
            ->assertJsonPath('data.recent_quotations.0.total', 200)
            ->assertJsonPath('data.recent_quotations.0.status', 'pending')
            ->assertJsonPath('data.low_stock_products.0.uid', $product->uid)
            ->assertJsonPath('data.low_stock_products.0.current_stock', 3)
            ->assertJsonPath('data.low_stock_products.0.minimum_stock', 25)
            ->assertJsonPath('data.low_stock_products.0.stock_status', 'critical')
            ->assertJsonCount(12, 'data.monthly_sales')
            ->assertJsonPath('data.monthly_sales.11.month', now()->format('Y-m'))
            ->assertJsonPath('data.monthly_sales.11.label', 'May')
            ->assertJsonPath('data.monthly_sales.11.actual', 200)
            ->assertJsonPath('data.monthly_sales.11.goal', null);
    }

    public function test_activities_can_return_tenant_scope_without_pagination(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme Corporation',
            'is_active' => true,
        ]);
        $user = $this->authenticateTenantUser($tenant, ['activities.read']);
        $otherUser = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Laura M.',
            'email' => 'laura@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $activity = Activity::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $otherUser->getKey(),
            'assigned_user_id' => $otherUser->getKey(),
            'type' => 'task',
            'title' => 'Llamada de seguimiento',
            'status' => 'pending',
            'scheduled_at' => now()->addHour(),
        ]);

        $this->getJson('/api/activities?scope=tenant&per_page=10&paginate=false')
            ->assertOk()
            ->assertJsonPath('data.0.uid', $activity->uid)
            ->assertJsonPath('meta', null);

        $this->getJson('/api/activities?per_page=10&paginate=false')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function authenticateTenantUser(Tenant $tenant, array $permissionKeys, bool $ownerRole = false): User
    {
        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'dashboard',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Carlos V.',
            'email' => 'carlos@example.test',
            'password' => bcrypt('secret123'),
            'two_factor_secret' => 'secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        if ($ownerRole) {
            $role = Role::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Owner',
                'key' => 'owner',
                'is_system' => true,
            ]);
            $user->roles()->attach($role->getKey());
        }

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
