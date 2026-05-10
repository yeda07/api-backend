<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CommissionAssignment;
use App\Models\CommissionEntry;
use App\Models\CommissionPlan;
use App\Models\CommissionRun;
use App\Models\CommissionTarget;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionsBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_frontend_aliases_detail_update_simulation_and_delete_work(): void
    {
        $this->authenticateWithPermissions(['commissions.read', 'commissions.manage']);

        $create = $this->postJson('/api/commissions/plans', [
            'name' => 'Plan ventas frontend',
            'base_percentage' => 5,
            'tiers' => [
                ['threshold' => 50000, 'percentage' => 7],
            ],
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Plan ventas frontend')
            ->assertJsonPath('data.type', 'sale')
            ->assertJsonPath('data.base_percentage', 5)
            ->assertJsonPath('data.tiers.0.percentage', 7)
            ->assertJsonPath('data.is_active', true);

        $uid = $create->json('data.uid');

        $this->getJson('/api/commissions/plans/' . $uid)
            ->assertOk()
            ->assertJsonPath('data.uid', $uid);

        $this->putJson('/api/commissions/plans/' . $uid, [
            'base_percentage' => 6,
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.base_percentage', 6)
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/api/commissions/simulate', [
            'plan_uid' => $uid,
            'total_sales' => 85000,
        ])
            ->assertOk()
            ->assertJsonPath('data.plan_uid', $uid)
            ->assertJsonPath('data.commission_amount', 5950)
            ->assertJsonPath('data.effective_percentage', 7)
            ->assertJsonPath('data.tier_applied', 1);

        $this->deleteJson('/api/commissions/plans/' . $uid)
            ->assertOk()
            ->assertJsonPath('message', 'Plan de comision eliminado');

        $this->assertDatabaseMissing('commission_plans', ['uid' => $uid]);
    }

    public function test_assignments_and_targets_have_detail_update_and_delete_contracts(): void
    {
        $user = $this->authenticateWithPermissions(['commissions.read', 'commissions.manage']);
        $seller = User::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Vendedor Comisiones',
            'email' => 'seller-commissions+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $planUid = $this->postJson('/api/commissions/plans', [
            'name' => 'Plan asignable',
            'base_percentage' => 4,
        ])->assertCreated()->json('data.uid');

        $assignment = $this->postJson('/api/commissions/assignments', [
            'user_uid' => $seller->uid,
            'commission_plan_uid' => $planUid,
            'starts_at' => '2026-01-01',
        ]);

        $assignment->assertCreated()
            ->assertJsonPath('data.user_uid', $seller->uid)
            ->assertJsonPath('data.plan_uid', $planUid)
            ->assertJsonPath('data.status', 'active');

        $assignmentUid = $assignment->json('data.uid');

        $this->getJson('/api/commissions/assignments/' . $assignmentUid)
            ->assertOk()
            ->assertJsonPath('data.uid', $assignmentUid);

        $target = $this->postJson('/api/commissions/targets', [
            'user_uid' => $seller->uid,
            'metric' => 'total_sales',
            'period' => '2026-Q1',
            'goal_value' => 120000,
        ]);

        $target->assertCreated()
            ->assertJsonPath('data.user_uid', $seller->uid)
            ->assertJsonPath('data.metric', 'total_sales')
            ->assertJsonPath('data.goal_value', 120000);

        $targetUid = $target->json('data.uid');

        $this->getJson('/api/commissions/targets/' . $targetUid)
            ->assertOk()
            ->assertJsonPath('data.uid', $targetUid);

        $this->putJson('/api/commissions/targets/' . $targetUid, [
            'goal_value' => 150000,
        ])
            ->assertOk()
            ->assertJsonPath('data.goal_value', 150000);

        $this->deleteJson('/api/commissions/targets/' . $targetUid)
            ->assertOk()
            ->assertJsonPath('message', 'Meta de comision eliminada');

        $this->deleteJson('/api/commissions/assignments/' . $assignmentUid)
            ->assertOk()
            ->assertJsonPath('message', 'Asignacion de comision eliminada');
    }

    public function test_simple_financial_record_payload_and_entry_frontend_status_work(): void
    {
        $user = $this->authenticateWithPermissions(['commissions.read', 'commissions.manage']);

        $this->postJson('/api/commissions/financial-records', [
            'type' => 'sale',
            'amount' => 15000,
            'description' => 'Venta manual',
            'recorded_at' => '2026-05-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.financial_record.amount', '15000.00')
            ->assertJsonPath('data.financial_record.record_type', 'collection_received')
            ->assertJsonPath('data.financial_record.meta.description', 'Venta manual')
            ->assertJsonPath('data.summary.entries_count', 0);

        CommissionEntry::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->getKey(),
            'base_amount' => 1000,
            'rate_percent' => 5,
            'commission_amount' => 50,
            'status' => 'earned',
            'earned_at' => '2026-05-02',
        ]);

        $this->getJson('/api/commissions/entries')
            ->assertOk()
            ->assertJsonPath('data.0.frontend_status', 'pending');
    }

    public function test_commissions_dashboard_matches_frontend_contract(): void
    {
        $user = $this->authenticateWithPermissions(['commissions.read', 'commissions.manage']);
        $seller = User::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Vendedora Dashboard',
            'email' => 'seller-dashboard+'.uniqid().'@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $period = now()->format('Y-m');
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        $plan = CommissionPlan::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Plan Dashboard',
            'type' => 'sale',
            'base_percent' => 5,
            'tiers_json' => [
                ['threshold' => 50000, 'percent' => 7],
                ['threshold' => 100000, 'percent' => 10],
            ],
            'active' => true,
        ]);

        CommissionAssignment::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $seller->getKey(),
            'commission_plan_id' => $plan->getKey(),
            'starts_at' => $periodStart,
            'active' => true,
        ]);

        CommissionTarget::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $seller->getKey(),
            'period' => $period,
            'target_amount' => 120000,
        ]);

        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $seller->getKey(),
            'name' => 'Cliente Dashboard',
            'document' => 'COM-DASH-'.uniqid(),
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $seller->getKey(),
            'quoteable_type' => Account::class,
            'quoteable_id' => $account->getKey(),
            'quote_number' => 'Q-DASH-'.uniqid(),
            'title' => 'Venta dashboard',
            'status' => 'approved',
        ]);

        CommissionEntry::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $seller->getKey(),
            'quotation_id' => $quotation->getKey(),
            'base_amount' => 75000,
            'rate_percent' => 7,
            'commission_amount' => 5250,
            'status' => 'earned',
            'earned_at' => now()->toDateString(),
        ]);

        CommissionRun::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $seller->getKey(),
            'commission_plan_id' => $plan->getKey(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'sales_amount' => 75000,
            'commission_amount' => 3000,
            'status' => 'approved',
        ]);

        $this->getJson('/api/commissions/dashboard/'.$seller->uid)
            ->assertOk()
            ->assertJsonStructure(['data' => ['kpis', 'tiers', 'recentSales']])
            ->assertJsonPath('data.kpis.monthly_target', 120000)
            ->assertJsonPath('data.kpis.sales_achieved', 75000)
            ->assertJsonPath('data.kpis.projected_commission', 5250)
            ->assertJsonPath('data.kpis.liquidated_commission', 3000)
            ->assertJsonPath('data.tiers.0.name', 'Tramo 1')
            ->assertJsonPath('data.tiers.0.status', 'COMPLETED')
            ->assertJsonPath('data.tiers.0.completed', 100)
            ->assertJsonPath('data.tiers.1.status', 'IN_PROGRESS')
            ->assertJsonPath('data.tiers.1.completed', 50)
            ->assertJsonPath('data.recentSales.0.client', 'Cliente Dashboard')
            ->assertJsonPath('data.recentSales.0.amount', 75000)
            ->assertJsonPath('data.recentSales.0.commission_generated', 5250)
            ->assertJsonMissingPath('data.monthly_target')
            ->assertJsonMissingPath('data.recent_entries');
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Comisiones',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'commissions',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Commissions Owner',
            'email' => 'commissions-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        if ($permissionKeys !== []) {
            $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
            $user->permissions()->sync($permissionIds);
        }

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
