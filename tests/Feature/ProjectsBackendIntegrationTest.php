<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectsBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_accept_frontend_contract_fields(): void
    {
        $user = $this->authenticateWithPermissions(['projects.read', 'projects.manage']);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'TechMex Solutions',
            'document' => 'TECH-' . uniqid(),
        ]);
        $assignee = User::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Carlos Valencia',
            'email' => 'carlos.projects+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $project = $this->postJson('/api/projects', [
            'client_uid' => $account->uid,
            'name' => 'Implementacion CRM - TechMex',
            'description' => 'Fase 1 de implementacion',
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to_uid' => $assignee->uid,
            'start_date' => '2026-01-15',
            'end_date' => '2026-04-30',
            'estimated_hours' => 120,
        ]);

        $project->assertCreated()
            ->assertJsonPath('data.name', 'Implementacion CRM - TechMex')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.client_uid', $account->uid)
            ->assertJsonPath('data.client_name', 'TechMex Solutions')
            ->assertJsonPath('data.assigned_to_uid', $assignee->uid)
            ->assertJsonPath('data.assigned_to_name', 'Carlos Valencia')
            ->assertJsonPath('data.estimated_hours', 120)
            ->assertJsonPath('data.actual_hours', 0);

        $uid = $project->json('data.uid');

        $this->getJson('/api/projects/' . $uid)
            ->assertOk()
            ->assertJsonPath('data.client_name', 'TechMex Solutions')
            ->assertJsonPath('data.assigned_to_name', 'Carlos Valencia');

        $this->putJson('/api/projects/' . $uid, [
            'status' => 'on_hold',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'on_hold');
    }

    public function test_milestones_assignments_team_and_progress_match_frontend_contract(): void
    {
        $user = $this->authenticateWithPermissions(['projects.read', 'projects.manage']);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'Cuenta Proyecto',
            'document' => 'PROJ-' . uniqid(),
        ]);
        $member = User::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Laura Mendez',
            'email' => 'laura.projects+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $projectUid = $this->postJson('/api/projects', [
            'client_uid' => $account->uid,
            'name' => 'Proyecto Frontend',
            'status' => 'planning',
        ])->assertCreated()->json('data.uid');

        $milestone = $this->postJson('/api/projects/' . $projectUid . '/milestones', [
            'title' => 'Kickoff meeting',
            'description' => 'Reunion inicial con el cliente',
            'status' => 'completed',
            'due_date' => '2026-01-20',
            'assigned_to_uid' => $member->uid,
        ]);

        $milestone->assertCreated()
            ->assertJsonPath('data.title', 'Kickoff meeting')
            ->assertJsonPath('data.name', 'Kickoff meeting')
            ->assertJsonPath('data.status', 'completed');

        $this->putJson('/api/milestones/' . $milestone->json('data.uid'), [
            'title' => 'Kickoff actualizado',
            'status' => 'in_progress',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Kickoff actualizado')
            ->assertJsonPath('data.status', 'in_progress');

        $this->putJson('/api/milestones/' . $milestone->json('data.uid'), [
            'status' => 'completed',
        ])->assertOk();

        $assignment = $this->postJson('/api/projects/' . $projectUid . '/assignments', [
            'user_uid' => $member->uid,
            'role' => 'developer',
        ]);

        $assignment->assertCreated()
            ->assertJsonPath('data.user_uid', $member->uid)
            ->assertJsonPath('data.user_name', 'Laura Mendez')
            ->assertJsonPath('data.role', 'developer')
            ->assertJsonPath('data.hours_allocated', '0.00');

        $this->getJson('/api/projects/' . $projectUid . '/team')
            ->assertOk()
            ->assertJsonPath('data.0.user_name', 'Laura Mendez')
            ->assertJsonPath('data.0.role', 'developer');

        $this->getJson('/api/projects/' . $projectUid . '/progress')
            ->assertOk()
            ->assertJsonPath('data.completion_pct', 100)
            ->assertJsonPath('data.milestones_total', 1)
            ->assertJsonPath('data.milestones_completed', 1)
            ->assertJsonPath('data.hours_estimated', 0)
            ->assertJsonPath('data.hours_logged', 0);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Projects',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => 'projects',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Projects Owner',
            'email' => 'projects-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
