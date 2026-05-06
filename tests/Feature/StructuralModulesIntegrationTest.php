<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Activity;
use App\Models\AutomationRule;
use App\Models\Contact;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructuralModulesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_segments_crud_and_run_evaluate_backend_rules(): void
    {
        $user = $this->authenticateWithPermissions(['segments.read', 'segments.manage']);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'B2B Activo',
            'document' => 'DOC-' . uniqid(),
            'industry' => 'software',
        ]);
        Contact::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'first_name' => 'Carlos',
            'last_name' => 'Segmentado',
            'email' => 'segmentado@example.test',
            'position' => 'buyer',
        ]);
        Contact::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'first_name' => 'Ana',
            'last_name' => 'No Match',
            'email' => 'no-match@example.test',
            'position' => 'other',
        ]);

        $response = $this->postJson('/api/segments', [
            'name' => 'Compradores',
            'entity_type' => 'contact',
            'logic' => 'AND',
            'rules' => [
                ['field' => 'position', 'operator' => 'equals', 'value' => 'buyer'],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Compradores');
        $uid = $response->json('data.uid');

        $this->postJson('/api/segments/' . $uid . '/run')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.data.0.email', 'segmentado@example.test')
            ->assertJsonPath('data.segment.execution_count', 1);
    }

    public function test_teams_crud_manages_manager_and_members(): void
    {
        $owner = $this->authenticateWithPermissions(['teams.read', 'teams.manage']);
        $manager = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Manager',
            'email' => 'manager-team@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $member = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Member',
            'email' => 'member-team@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/teams', [
            'name' => 'Ventas Norte',
            'description' => 'Equipo comercial',
            'manager_uid' => $manager->uid,
            'member_uids' => [$member->uid],
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.manager_uid', $manager->uid)
            ->assertJsonPath('data.member_uids.0', $member->uid);

        $uid = $response->json('data.uid');

        $this->putJson('/api/teams/' . $uid, ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_automation_rules_assignment_rules_and_engine_are_available(): void
    {
        Mail::fake();
        Http::fake();

        $owner = $this->authenticateWithPermissions([
            'automation.read',
            'automation.create',
            'automation.update',
            'automation.delete',
        ]);
        $assigned = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Asignado',
            'email' => 'asignado-auto@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $rule = $this->postJson('/api/automation/rules', [
            'name' => 'Lead web',
            'trigger_source' => 'lead_created',
            'conditions' => [
                ['field' => 'source', 'operator' => 'equals', 'value' => 'web_form'],
            ],
            'actions' => [
                ['type' => 'create_task', 'config' => ['title' => 'Contactar lead', 'priority' => 'high']],
            ],
            'logic' => 'AND',
            'is_active' => true,
        ]);

        $rule->assertCreated()->assertJsonPath('data.execution_count', 0);

        $assignment = $this->postJson('/api/automation/assignment-rules', [
            'name' => 'Pais Colombia',
            'conditions' => [
                ['field' => 'country', 'operator' => 'equals', 'value' => 'Colombia'],
            ],
            'assigned_to_uid' => $assigned->uid,
            'logic' => 'AND',
            'is_active' => true,
        ]);

        $assignment
            ->assertCreated()
            ->assertJsonPath('data.assigned_to_uid', $assigned->uid)
            ->assertJsonPath('data.assigned_to_name', 'Asignado');

        $result = app(AutomationService::class)->execute('lead_created', ['source' => 'web_form']);

        $this->assertSame(1, $result['executed']);
        $this->assertSame(1, Activity::query()->where('title', 'Contactar lead')->count());
        $this->assertSame(1, AutomationRule::query()->first()->execution_count);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Structural',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'structural',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Structural Owner',
            'email' => 'structural-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
