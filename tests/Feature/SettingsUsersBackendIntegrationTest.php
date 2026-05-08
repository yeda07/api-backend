<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsUsersBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_deactivated_and_activated_from_update_endpoint(): void
    {
        $owner = $this->authenticateWithPermissions(['users.manage']);
        $user = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Usuario Settings',
            'email' => 'settings-user+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->putJson('/api/users/' . $user->uid, [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'INACTIVO');

        $this->assertNotNull($user->fresh()->locked_until);

        $this->getJson('/api/users?estado=INACTIVO')
            ->assertOk()
            ->assertJsonPath('data.0.uid', $user->uid);

        $this->putJson('/api/users/' . $user->uid, [
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.status', 'ACTIVO');

        $this->assertNull($user->fresh()->locked_until);
    }

    public function test_user_status_alias_can_toggle_activity(): void
    {
        $owner = $this->authenticateWithPermissions(['users.manage']);
        $user = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Usuario Alias',
            'email' => 'settings-user-alias+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->putJson('/api/users/' . $user->uid, [
            'status' => 'INACTIVO',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'INACTIVO');

        $this->putJson('/api/users/' . $user->uid, [
            'active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.status', 'ACTIVO');
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Settings Users',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => 'users',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Settings Users Owner',
            'email' => 'settings-users-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
