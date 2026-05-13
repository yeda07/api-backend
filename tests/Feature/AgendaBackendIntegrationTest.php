<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendaBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_crud_accepts_frontend_agenda_contract(): void
    {
        $user = $this->authenticateWithPermissions(['activities.read', 'activities.create', 'activities.update', 'activities.delete']);
        $assigned = User::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Laura Mendez',
            'email' => 'laura-agenda@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'TechMex Solutions',
            'document' => 'DOC-'.uniqid(),
        ]);
        $contact = Contact::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'first_name' => 'Carlos',
            'last_name' => 'Valencia',
            'email' => 'carlos-agenda@example.test',
        ]);

        $response = $this->postJson('/api/activities', [
            'type' => 'call',
            'title' => 'Llamada de seguimiento',
            'description' => 'Contactar para renovacion',
            'status' => 'in_progress',
            'priority' => 'high',
            'scheduled_at' => now()->addDay()->toISOString(),
            'contact_uid' => $contact->uid,
            'account_uid' => $account->uid,
            'assigned_to_uid' => $assigned->uid,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'call')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.contact_uid', $contact->uid)
            ->assertJsonPath('data.contact_name', 'Carlos Valencia')
            ->assertJsonPath('data.account_uid', $account->uid)
            ->assertJsonPath('data.account_name', 'TechMex Solutions')
            ->assertJsonPath('data.assigned_to_uid', $assigned->uid)
            ->assertJsonPath('data.assigned_to_name', 'Laura Mendez');

        $activityUid = $response->json('data.uid');

        $this->putJson('/api/activities/'.$activityUid, [
            'type' => 'email',
            'status' => 'cancelled',
            'priority' => 'low',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'email')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.priority', 'low');

        $this->deleteJson('/api/activities/'.$activityUid)->assertOk();
    }

    public function test_activity_range_accepts_start_and_end_aliases(): void
    {
        $user = $this->authenticateWithPermissions(['activities.read']);

        $included = Activity::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'type' => 'meeting',
            'title' => 'Reunion incluida',
            'status' => 'pending',
            'priority' => 'medium',
            'scheduled_at' => '2026-05-10 10:00:00',
        ]);
        Activity::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'type' => 'note',
            'title' => 'Nota fuera',
            'status' => 'pending',
            'priority' => 'medium',
            'scheduled_at' => '2026-05-20 10:00:00',
        ]);

        $this->getJson('/api/activities/range?start=2026-05-01&end=2026-05-15')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uid', $included->uid);
    }

    public function test_schedule_endpoint_merges_agenda_and_project_items(): void
    {
        $user = $this->authenticateWithPermissions(['activities.read']);
        $activity = Activity::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'type' => 'meeting',
            'title' => 'Demo agenda unificada',
            'status' => 'pending',
            'priority' => 'medium',
            'scheduled_at' => '2026-05-10 10:00:00',
        ]);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'Cliente Proyecto Agenda',
            'document' => 'AGENDA-'.uniqid(),
        ]);
        $project = Project::query()->create([
            'tenant_id' => $user->tenant_id,
            'account_id' => $account->getKey(),
            'name' => 'Proyecto Agenda',
            'status' => 'active',
        ]);
        $milestone = ProjectMilestone::query()->create([
            'tenant_id' => $user->tenant_id,
            'project_id' => $project->getKey(),
            'name' => 'Hito agenda unificada',
            'status' => 'in_progress',
            'due_date' => '2026-05-20',
        ]);

        $this->getJson('/api/schedule?search=agenda&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.uid', $activity->uid)
            ->assertJsonPath('data.0.source', 'agenda')
            ->assertJsonPath('data.1.uid', $milestone->uid)
            ->assertJsonPath('data.1.source', 'project');

        $this->getJson('/api/schedule?source=project&status=in_progress')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uid', $milestone->uid);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Agenda',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => 'activities',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Agenda Owner',
            'email' => 'agenda-owner+'.uniqid().'@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:'.$tenant->uid]);

        return $user;
    }
}
