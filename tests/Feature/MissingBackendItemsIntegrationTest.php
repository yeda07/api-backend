<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MissingBackendItemsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_init_includes_tasks_expenses_and_purchases_modules(): void
    {
        $this->authenticateWithPermissions([
            'tasks.read',
            'expenses.read',
            'purchases.read',
        ]);

        $this->getJson('/api/auth/init')
            ->assertOk()
            ->assertJsonFragment(['key' => 'tasks', 'label' => 'Tareas', 'enabled' => true])
            ->assertJsonFragment(['key' => 'expenses', 'label' => 'Gastos', 'enabled' => true])
            ->assertJsonFragment(['key' => 'purchases', 'label' => 'Compras', 'enabled' => true]);
    }

    public function test_opportunity_board_accepts_real_pagination(): void
    {
        $user = $this->authenticateWithPermissions(['opportunities.read']);
        $stage = OpportunityStage::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Leads',
            'key' => 'leads-test',
            'position' => 1,
            'is_active' => true,
        ]);

        foreach (['Uno', 'Dos'] as $title) {
            Opportunity::query()->create([
                'tenant_id' => $user->tenant_id,
                'owner_user_id' => $user->getKey(),
                'stage_id' => $stage->getKey(),
                'title' => $title,
                'amount' => 100,
            ]);
        }

        $this->getJson('/api/opportunities/board?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_document_types_can_be_deleted(): void
    {
        $user = $this->authenticateWithPermissions(['documents.manage']);
        $documentType = DocumentType::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Contrato eliminable',
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->deleteJson('/api/document-types/' . $documentType->uid)
            ->assertOk()
            ->assertJsonPath('message', 'Tipo de documento eliminado');

        $this->assertDatabaseMissing('document_types', ['id' => $documentType->getKey()]);
    }

    public function test_requested_export_endpoints_return_downloads(): void
    {
        $this->authenticateWithPermissions([
            'reports.read',
            'contacts.read',
            'inventory.read',
            'finance.read',
        ]);

        foreach ([
            '/api/reports/sales/export',
            '/api/reports/inventory/export',
            '/api/contacts/export',
            '/api/inventory/products/export',
            '/api/inventory/stock/export',
            '/api/sales/finance/invoices/export',
        ] as $endpoint) {
            $response = $this->postJson($endpoint, ['format' => 'csv']);

            $response->assertOk();
            $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        }
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Missing Items',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'missing',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Missing Owner',
            'email' => 'missing-owner+' . uniqid() . '@example.test',
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
