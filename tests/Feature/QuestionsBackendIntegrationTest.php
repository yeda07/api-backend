<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Document;
use App\Models\Interaction;
use App\Models\Partner;
use App\Models\PartnerOpportunity;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuestionsBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_fields_have_full_crud(): void
    {
        $this->authenticateWithPermissions(['custom-fields.manage']);

        $create = $this->postJson('/api/custom-fields', [
            'entity_type' => 'account',
            'name' => 'Region',
            'key' => 'region',
            'type' => 'text',
            'options' => ['required' => true],
        ]);

        $create->assertCreated()->assertJsonPath('data.name', 'Region');
        $uid = $create->json('data.uid');

        $this->getJson('/api/custom-fields?entity_type=account')
            ->assertOk()
            ->assertJsonPath('data.0.uid', $uid);

        $this->putJson('/api/custom-fields/' . $uid, [
            'name' => 'Region comercial',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Region comercial');

        $this->deleteJson('/api/custom-fields/' . $uid)
            ->assertOk()
            ->assertJsonPath('message', 'Campo eliminado');
    }

    public function test_localization_settings_can_be_read_and_updated(): void
    {
        $user = $this->authenticateWithPermissions([]);
        Currency::query()->create([
            'code' => 'COP',
            'name' => 'Colombian Peso',
            'symbol' => '$',
        ]);

        $this->putJson('/api/settings/localization', [
            'timezone' => 'America/Bogota',
            'currency' => 'COP',
            'date_format' => 'd/m/Y',
            'user_timezone' => 'America/Bogota',
        ])
            ->assertOk()
            ->assertJsonPath('data.timezone', 'America/Bogota')
            ->assertJsonPath('data.currency', 'COP')
            ->assertJsonPath('data.date_format', 'DD/MM/YYYY')
            ->assertJsonPath('data.user_timezone', 'America/Bogota');

        $this->assertSame('America/Bogota', $user->fresh()->timezone);
    }

    public function test_flat_interactions_and_documents_endpoints_exist(): void
    {
        $user = $this->authenticateWithPermissions(['interactions.read', 'documents.read', 'documents.manage']);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'Cuenta Docs',
            'document' => 'DOC-' . uniqid(),
        ]);

        Interaction::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'actor_user_id' => $user->getKey(),
            'type' => 'note',
            'subject' => 'Nota',
            'content' => 'Contenido',
            'occurred_at' => now(),
            'interactable_type' => Account::class,
            'interactable_id' => $account->getKey(),
        ]);

        $document = Document::query()->create([
            'tenant_id' => $user->tenant_id,
            'account_id' => $account->getKey(),
            'owner_user_id' => $user->getKey(),
            'uploaded_by_user_id' => $user->getKey(),
            'disk' => 'local',
            'path' => 'documents/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'status' => 'valid',
            'is_active' => true,
            'version_number' => 1,
            'documentable_type' => Account::class,
            'documentable_id' => $account->getKey(),
        ]);

        $this->getJson('/api/interactions')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'note');

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonPath('data.0.uid', $document->uid);

        $this->deleteJson('/api/documents/' . $document->uid)
            ->assertOk()
            ->assertJsonPath('message', 'Documento eliminado');
    }

    public function test_partner_opportunity_aliases_work(): void
    {
        $user = $this->authenticateWithPermissions(['partners.opportunities.manage']);
        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'Cuenta Partner',
            'document' => 'DOC-' . uniqid(),
        ]);
        $partner = Partner::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Partner A',
            'type' => 'reseller',
            'status' => 'active',
        ]);

        foreach (['approve' => 'won', 'reject' => 'lost', 'convert' => 'won'] as $action => $status) {
            $opportunity = PartnerOpportunity::query()->create([
                'tenant_id' => $user->tenant_id,
                'partner_id' => $partner->getKey(),
                'account_id' => $account->getKey(),
                'title' => 'Oportunidad ' . $action,
                'status' => 'open',
            ]);

            $this->postJson('/api/partners/opportunities/' . $opportunity->uid . '/' . $action)
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }
    }

    public function test_reports_aliases_are_available(): void
    {
        $this->authenticateWithPermissions(['finance.read', 'inventory.report']);

        $this->getJson('/api/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.monthly_sales', 0);

        $this->getJson('/api/reports/inventory')
            ->assertOk()
            ->assertJsonPath('data.rupture_risk.critical_products_count', 0);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Questions',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'questions',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Questions Owner',
            'email' => 'questions-owner+' . uniqid() . '@example.test',
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
