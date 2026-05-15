<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\SystemLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabaseRequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requests_are_saved_to_system_logs(): void
    {
        config(['app.env' => 'testing']);
        config(['performance.log_db_requests' => true]);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant Logs',
            'status' => 'active',
            'is_active' => true,
        ]);

        Permission::query()->firstOrCreate(
            ['key' => 'dashboard.read'],
            [
                'module' => 'dashboard',
                'action' => 'read',
                'description' => 'dashboard.read',
            ]
        );

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Log User',
            'email' => 'log-user@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->permissions()->attach(Permission::query()->where('key', 'dashboard.read')->value('id'));

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        $this->getJson('/api/dashboard/core')->assertOk();

        $this->assertDatabaseHas('system_logs', [
            'tenant_id' => $tenant->getKey(),
            'level' => 'info',
            'message' => 'API request',
        ]);

        $log = SystemLog::withoutGlobalScopes()->where('message', 'API request')->latest()->first();

        $this->assertSame('/api/dashboard/core', $log->context['path']);
        $this->assertSame('GET', $log->context['method']);
        $this->assertSame(200, $log->context['status']);
        $this->assertSame($user->uid, $log->context['user_uid']);
    }
}
