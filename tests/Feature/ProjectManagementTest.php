<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_can_be_created_and_progress_is_calculated(): void
    {
        $user = $this->authenticateWithPermissions(['projects.read', 'projects.manage']);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cuenta Proyecto',
            'document' => 'DOC-' . uniqid(),
            'email' => 'cuenta-proyecto+' . uniqid() . '@example.test',
        ]);

        $projectResponse = $this->postJson('/api/projects', [
            'account_uid' => $account->uid,
            'name' => 'Implementacion CRM',
            'status' => 'active',
            'start_date' => '2026-04-01',
            'end_date' => '2026-05-01',
        ]);

        $projectResponse->assertCreated()->assertJsonPath('success', true);
        $projectUid = $projectResponse->json('data.uid');

        $this->postJson('/api/projects/' . $projectUid . '/milestones', [
            'name' => 'Kickoff',
            'status' => 'done',
            'order' => 1,
        ])->assertCreated();

        $this->postJson('/api/projects/' . $projectUid . '/milestones', [
            'name' => 'Capacitacion',
            'status' => 'pending',
            'order' => 2,
        ])->assertCreated();

        $this->getJson('/api/projects/' . $projectUid . '/progress')
            ->assertOk()
            ->assertJsonPath('data.progress_percent', 50)
            ->assertJsonPath('data.milestones.done', 1)
            ->assertJsonPath('data.milestones.total', 2);
    }

    public function test_project_assignments_cannot_be_duplicated(): void
    {
        $user = $this->authenticateWithPermissions(['projects.read', 'projects.manage']);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cuenta Equipo',
            'document' => 'DOC-' . uniqid(),
            'email' => 'cuenta-equipo+' . uniqid() . '@example.test',
        ]);

        $project = Project::query()->create([
            'tenant_id' => $user->tenant_id,
            'account_id' => $account->getKey(),
            'name' => 'Proyecto Equipo',
            'status' => 'pending',
        ]);

        $member = User::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Consultor',
            'email' => 'consultor@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $payload = [
            'user_uid' => $member->uid,
            'role' => 'consultant',
            'hours_allocated' => 16,
        ];

        $this->postJson('/api/projects/' . $project->uid . '/assignments', $payload)->assertCreated();
        $this->postJson('/api/projects/' . $project->uid . '/assignments', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_won_opportunity_creates_project_automatically(): void
    {
        $user = $this->authenticateWithPermissions(['projects.read', 'projects.manage', 'opportunities.manage']);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cuenta Won',
            'document' => 'DOC-' . uniqid(),
            'email' => 'cuenta-won+' . uniqid() . '@example.test',
        ]);

        $openStage = OpportunityStage::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Abierta',
            'key' => 'open',
            'position' => 1,
        ]);

        $wonStage = OpportunityStage::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Ganada',
            'key' => 'won',
            'position' => 2,
            'is_won' => true,
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'stage_id' => $openStage->getKey(),
            'opportunityable_type' => Account::class,
            'opportunityable_id' => $account->getKey(),
            'title' => 'Venta cerrable',
        ]);

        $this->putJson('/api/opportunities/' . $opportunity->uid, [
            'stage_uid' => $wonStage->uid,
        ])->assertOk();

        $project = Project::query()->where('opportunity_id', $opportunity->getKey())->first();

        $this->assertNotNull($project);
        $this->assertSame($account->getKey(), $project->account_id);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Test',
            'slug' => 'tenant-test',
            'status' => 'active',
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => 'projects',
                    'action' => 'manage',
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Tester',
            'email' => 'tester+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
