<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitiveIntelligenceBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_competitors_and_battlecards_accept_frontend_contract(): void
    {
        $this->authenticateWithPermissions([
            'competitive-intelligence.read',
            'competitive-intelligence.manage',
        ]);

        $competitor = $this->postJson('/api/competitive-intelligence/competitors', [
            'name' => 'SalesForce CRM',
            'description' => 'CRM lider en mercado enterprise',
            'website' => 'https://salesforce.com',
            'strengths' => ['Marca reconocida'],
            'weaknesses' => ['Precio elevado'],
            'strength_score' => 8,
        ]);

        $competitor->assertCreated()
            ->assertJsonPath('data.name', 'SalesForce CRM')
            ->assertJsonPath('data.key', 'salesforce-crm')
            ->assertJsonPath('data.description', 'CRM lider en mercado enterprise')
            ->assertJsonPath('data.strength_score', 2);

        $competitorUid = $competitor->json('data.uid');

        $battlecard = $this->postJson('/api/competitive-intelligence/battlecards', [
            'competitor_uid' => $competitorUid,
            'competitor_name' => 'SalesForce CRM',
            'title' => 'Battlecard vs SalesForce',
            'description' => 'Estrategia competitiva',
            'strengths' => ['Marca reconocida'],
            'weaknesses' => ['Precio elevado'],
            'objections' => [
                ['objection' => 'Es muy caro', 'response' => 'Nuestro TCO es menor'],
            ],
        ]);

        $battlecard->assertCreated()
            ->assertJsonPath('data.competitor_uid', $competitorUid)
            ->assertJsonPath('data.competitor_name', 'SalesForce CRM')
            ->assertJsonPath('data.description', 'Estrategia competitiva')
            ->assertJsonPath('data.strengths.0', 'Marca reconocida')
            ->assertJsonPath('data.weaknesses.0', 'Precio elevado')
            ->assertJsonPath('data.objections.0.objection', 'Es muy caro');

        $this->getJson('/api/competitive-intelligence/competitors/' . $competitorUid . '/battlecards')
            ->assertOk()
            ->assertJsonPath('data.0.uid', $battlecard->json('data.uid'))
            ->assertJsonPath('data.0.competitor_name', 'SalesForce CRM');
    }

    public function test_lost_reasons_accept_frontend_contract_and_report(): void
    {
        $user = $this->authenticateWithPermissions([
            'competitive-intelligence.read',
            'competitive-intelligence.manage',
            'competitive-intelligence.report',
        ]);

        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'TechMex Solutions',
            'document' => 'TECH-123',
        ]);

        $competitorUid = $this->postJson('/api/competitive-intelligence/competitors', [
            'name' => 'SAP CRM',
            'description' => 'Competidor enterprise',
        ])->assertCreated()->json('data.uid');

        $lostReason = $this->postJson('/api/competitive-intelligence/lost-reasons', [
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'competitor_uid' => $competitorUid,
            'owner_user_uid' => $user->uid,
            'account_name' => 'TechMex Solutions',
            'deal_value' => 45000,
            'lost_reason_category' => 'Precio',
            'lost_reason_detail' => 'Competidor ofrecio 30% menos',
            'closed_date' => '2026-05-01',
            'sales_rep' => $user->name,
        ]);

        $lostReason->assertCreated()
            ->assertJsonPath('data.account_name', 'TechMex Solutions')
            ->assertJsonPath('data.deal_value', 45000)
            ->assertJsonPath('data.lost_reason_category', 'Precio')
            ->assertJsonPath('data.lost_reason_detail', 'Competidor ofrecio 30% menos')
            ->assertJsonPath('data.competitor_name', 'SAP CRM')
            ->assertJsonPath('data.sales_rep', $user->name);

        $this->getJson('/api/competitive-intelligence/lost-reasons')
            ->assertOk()
            ->assertJsonPath('data.0.uid', $lostReason->json('data.uid'))
            ->assertJsonPath('data.0.lost_reason_category', 'Precio');

        $this->getJson('/api/competitive-intelligence/lost-reasons/report')
            ->assertOk()
            ->assertJsonPath('data.summary.count', 1)
            ->assertJsonPath('data.summary.estimated_value_total', 45000);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Intelligence',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => 'competitive-intelligence',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Intelligence Owner',
            'email' => 'intelligence-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
