<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_normalize_color_and_store_entity_types(): void
    {
        $this->authenticateWithPermissions(['tags.manage']);

        $response = $this->postJson('/api/tags', [
            'name' => 'VIP',
            'key' => 'vip',
            'color' => 'green',
            'category' => 'general',
            'entity_types' => ['CONTACT', 'COMPANY'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.color', '#16a34a')
            ->assertJsonPath('data.entity_types.0', 'CONTACT')
            ->assertJsonPath('data.entity_types.1', 'COMPANY');

        $uid = $response->json('data.uid');

        $this->putJson('/api/tags/' . $uid, [
            'color' => 'blue',
            'entity_types' => ['DEAL'],
        ])
            ->assertOk()
            ->assertJsonPath('data.color', '#2563eb')
            ->assertJsonPath('data.entity_types.0', 'DEAL');
    }

    public function test_teams_member_endpoints_and_delete_guard_match_settings_contract(): void
    {
        $owner = $this->authenticateWithPermissions(['teams.read', 'teams.manage']);
        $leader = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Carlos Mendoza',
            'email' => 'leader-settings+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $member = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Laura Rios',
            'email' => 'member-settings+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);
        Account::query()->create([
            'tenant_id' => $owner->tenant_id,
            'owner_user_id' => $member->getKey(),
            'name' => 'Cliente Asignado',
            'document' => 'SET-' . uniqid(),
        ]);

        $team = $this->postJson('/api/teams', [
            'name' => 'Equipo Norte',
            'leader_uid' => $leader->uid,
        ]);

        $team->assertCreated()
            ->assertJsonPath('data.leader_uid', $leader->uid)
            ->assertJsonPath('data.leader_name', 'Carlos Mendoza')
            ->assertJsonPath('data.members_count', 0);

        $uid = $team->json('data.uid');

        $this->postJson('/api/teams/' . $uid . '/members', [
            'user_uid' => $member->uid,
        ])
            ->assertOk()
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.members.0.user_uid', $member->uid)
            ->assertJsonPath('data.members.0.user_name', 'Laura Rios')
            ->assertJsonPath('data.members.0.assigned_clients', 1);

        $this->deleteJson('/api/teams/' . $uid)
            ->assertUnprocessable();

        $this->deleteJson('/api/teams/' . $uid . '/members/' . $member->uid)
            ->assertOk()
            ->assertJsonPath('message', 'Member removed');

        $this->deleteJson('/api/teams/' . $uid)
            ->assertOk()
            ->assertJsonPath('message', 'Team deleted');
    }

    public function test_teams_index_filters_by_search(): void
    {
        $this->authenticateWithPermissions(['teams.read', 'teams.manage']);

        $this->postJson('/api/teams', [
            'name' => 'Equipo Norte',
        ])->assertCreated();

        $this->postJson('/api/teams', [
            'name' => 'Soporte Sur',
        ])->assertCreated();

        $this->getJson('/api/teams?search=norte')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Equipo Norte');
    }

    public function test_custom_fields_and_localization_accept_settings_frontend_aliases(): void
    {
        $this->authenticateWithPermissions(['custom-fields.manage', 'settings.manage']);
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => 'US$',
        ]);

        $field = $this->postJson('/api/custom-fields', [
            'module' => 'contacts',
            'label' => 'Codigo de Licitacion',
            'key' => 'codigo_licitacion',
            'type' => 'text',
            'required' => false,
        ]);

        $field->assertCreated()
            ->assertJsonPath('data.label', 'Codigo de Licitacion')
            ->assertJsonPath('data.module', 'contacts')
            ->assertJsonPath('data.required', false);

        $uid = $field->json('data.uid');

        $this->putJson('/api/custom-fields/' . $uid, [
            'label' => 'Codigo actualizado',
            'required' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Codigo actualizado')
            ->assertJsonPath('data.required', true);

        $this->putJson('/api/settings/localization', [
            'currency' => 'USD',
            'locale' => 'en-US',
            'timezone' => 'America/Bogota',
            'date_format' => 'Y-m-d',
        ])
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.currency_symbol', 'US$')
            ->assertJsonPath('data.locale', 'en-US')
            ->assertJsonPath('data.timezone', 'America/Bogota')
            ->assertJsonPath('data.date_format', 'YYYY-MM-DD');
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Settings',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'settings',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Settings Owner',
            'email' => 'settings-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
