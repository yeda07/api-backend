<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnersBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partners_accept_frontend_contract_fields(): void
    {
        $this->authenticateWithPermissions(['partners.read', 'partners.manage']);

        $partner = $this->postJson('/api/partners', [
            'name' => 'TechMex Solutions',
            'partner_type' => 'distributor',
            'status' => 'prospect',
            'contact_name' => 'Carlos Valencia',
            'contact_email' => 'carlos@techmex.test',
            'phone' => '+52 55 1234 5678',
            'region' => 'LATAM Norte',
            'notes' => 'Partner estrategico',
        ]);

        $partner->assertCreated()
            ->assertJsonPath('data.name', 'TechMex Solutions')
            ->assertJsonPath('data.type', 'distributor')
            ->assertJsonPath('data.partner_type', 'distributor')
            ->assertJsonPath('data.status', 'prospect')
            ->assertJsonPath('data.contact_name', 'Carlos Valencia')
            ->assertJsonPath('data.contact_email', 'carlos@techmex.test')
            ->assertJsonPath('data.phone', '+52 55 1234 5678')
            ->assertJsonPath('data.region', 'LATAM Norte')
            ->assertJsonPath('data.notes', 'Partner estrategico');

        $this->getJson('/api/partners')
            ->assertOk()
            ->assertJsonPath('data.0.partner_type', 'distributor');
    }

    public function test_partner_opportunities_validate_batch_and_close_without_body(): void
    {
        $this->authenticateWithPermissions([
            'partners.manage',
            'partners.opportunities.read',
            'partners.opportunities.manage',
        ]);

        $partnerUid = $this->postJson('/api/partners', [
            'name' => 'Canal MX',
            'partner_type' => 'reseller',
            'status' => 'active',
        ])->assertCreated()->json('data.uid');

        $opportunity = $this->postJson('/api/partners/opportunities', [
            'partner_uid' => $partnerUid,
            'partner_name' => 'Canal MX',
            'client_name' => 'Gobierno de CDMX',
            'client_email' => 'licitaciones@cdmx.test',
            'product' => 'CRM Enterprise',
            'estimated_value' => 45000,
            'currency' => 'USD',
            'registered_date' => '2026-05-07',
            'notes' => 'Licitacion publica 2026-0042',
        ]);

        $opportunity->assertCreated()
            ->assertJsonPath('data.partner_uid', $partnerUid)
            ->assertJsonPath('data.partner_name', 'Canal MX')
            ->assertJsonPath('data.client_name', 'Gobierno de CDMX')
            ->assertJsonPath('data.client_email', 'licitaciones@cdmx.test')
            ->assertJsonPath('data.product', 'CRM Enterprise')
            ->assertJsonPath('data.estimated_value', 45000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.notes', 'Licitacion publica 2026-0042');

        $uid = $opportunity->json('data.uid');

        $this->postJson('/api/partners/opportunities/validate', [
            'uids' => [$uid],
        ])
            ->assertOk()
            ->assertJsonPath('data.validated_count', 1)
            ->assertJsonPath('data.opportunities.0.status', 'validated');

        $this->postJson('/api/partners/opportunities/' . $uid . '/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_partner_resources_accept_material_type_alias(): void
    {
        Storage::fake('local');
        $this->authenticateWithPermissions([
            'partners.read',
            'partners.manage',
            'partners.resources.read',
            'partners.resources.manage',
        ]);

        $partnerUid = $this->postJson('/api/partners', [
            'name' => 'Canal Recursos',
            'partner_type' => 'ally',
            'status' => 'active',
        ])->assertCreated()->json('data.uid');

        $resource = $this->post('/api/partner-resources', [
            'title' => 'Deck Comercial Q1 2026',
            'material_type' => 'deck',
            'description' => 'Presentacion de ventas',
            'partner_uids' => [$partnerUid],
            'file' => UploadedFile::fake()->create('deck-q1-2026.pdf', 2400, 'application/pdf'),
        ]);

        $resource->assertCreated()
            ->assertJsonPath('data.title', 'Deck Comercial Q1 2026')
            ->assertJsonPath('data.type', 'sales')
            ->assertJsonPath('data.material_type', 'deck')
            ->assertJsonPath('data.file_name', 'deck-q1-2026.pdf')
            ->assertJsonPath('data.download_count', 0);

        $this->postJson('/api/partner-resources/' . $resource->json('data.uid') . '/assign', [
            'partner_uids' => [$partnerUid],
        ])->assertOk();
    }

    public function test_partner_option_endpoints_return_backend_supported_values(): void
    {
        $this->authenticateWithPermissions([
            'partners.read',
            'partners.resources.read',
            'partners.opportunities.read',
        ]);

        $this->getJson('/api/partners/types')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'distributor')
            ->assertJsonPath('data.0.value', 'distributor')
            ->assertJsonPath('data.1.key', 'reseller')
            ->assertJsonPath('data.2.key', 'ally');

        $this->getJson('/api/partners/opportunities/statuses')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'pending')
            ->assertJsonPath('data.1.key', 'validated')
            ->assertJsonPath('data.2.key', 'closed')
            ->assertJsonPath('data.3.key', 'won')
            ->assertJsonPath('data.4.key', 'lost')
            ->assertJsonPath('data.5.key', 'cancelled');

        $this->getJson('/api/partner-resources/types')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'sales')
            ->assertJsonPath('data.1.key', 'training');
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Partners',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => 'partners',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Partners Owner',
            'email' => 'partners-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
