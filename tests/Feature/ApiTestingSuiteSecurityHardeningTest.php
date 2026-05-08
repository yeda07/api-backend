<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTestingSuiteSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_activities_reject_unauthenticated_access(): void
    {
        $this->getJson('/api/activities')->assertUnauthorized();
    }

    public function test_activities_paginate_false_does_not_bypass_row_level_security(): void
    {
        $tenant = $this->tenant('Tenant A');
        $user = $this->userWithPermissions($tenant, ['activities.read']);
        $otherUser = $this->plainUser($tenant, 'otro-' . uniqid() . '@example.test');

        Activity::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $otherUser->getKey(),
            'assigned_user_id' => $otherUser->getKey(),
            'type' => 'task',
            'title' => 'Actividad privada de otro usuario',
            'status' => 'pending',
            'scheduled_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        $this->getJson('/api/activities?paginate=false&per_page=100')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_activity_assignment_cannot_use_user_from_another_tenant(): void
    {
        $tenantA = $this->tenant('Tenant A');
        $tenantB = $this->tenant('Tenant B');
        $userA = $this->userWithPermissions($tenantA, ['activities.create']);
        $userB = $this->plainUser($tenantB, 'tenant-b-' . uniqid() . '@example.test');

        Sanctum::actingAs($userA, ['access:full', 'tenant:' . $tenantA->uid]);

        $this->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Intento de asignacion cross-tenant',
            'scheduled_at' => now()->addDay()->toISOString(),
            'assigned_user_uid' => $userB->uid,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_user_uid');
    }

    public function test_forgot_password_does_not_reveal_if_email_exists(): void
    {
        $tenant = $this->tenant('Tenant Auth');
        $this->plainUser($tenant, 'existe-' . uniqid() . '@example.test');

        $missing = $this->postJson('/api/forgot-password', [
            'email' => 'noexiste-' . uniqid() . '@example.test',
        ]);
        $existing = $this->postJson('/api/forgot-password', [
            'email' => User::query()->first()->email,
        ]);

        $missing->assertOk();
        $existing->assertOk();
        $this->assertSame($missing->json('message'), $existing->json('message'));
    }

    public function test_security_headers_are_present_on_api_responses(): void
    {
        $tenant = $this->tenant('Tenant Headers');
        $user = $this->userWithPermissions($tenant, []);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    }

    private function tenant(string $name): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function userWithPermissions(Tenant $tenant, array $permissionKeys): User
    {
        $user = $this->plainUser($tenant, uniqid('secure-user-') . '@example.test');

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'security',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user->permissions()->sync(Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all());

        return $user;
    }

    private function plainUser(Tenant $tenant, string $email): User
    {
        return User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Security User',
            'email' => $email,
            'password' => bcrypt('secret123'),
            'two_factor_secret' => 'secret',
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
