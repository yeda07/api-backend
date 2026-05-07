<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactsBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_and_contacts_accept_frontend_aliases(): void
    {
        $this->authenticateWithPermissions([
            'accounts.read',
            'accounts.create',
            'contacts.read',
            'contacts.create',
        ]);

        $account = $this->postJson('/api/accounts', [
            'name' => 'TechMex Solutions',
            'tax_id' => 'TME-840215-ABC',
            'email' => 'contacto@techmex.test',
            'phone' => '+52 55 1234 5678',
            'industry' => 'Tecnologia',
            'website' => 'https://techmex.test',
            'status' => 'active',
            'country' => 'Mexico',
            'city' => 'CDMX',
            'company_size' => 'medium',
        ]);

        $account->assertCreated()
            ->assertJsonPath('data.name', 'TechMex Solutions')
            ->assertJsonPath('data.document', 'TME-840215-ABC')
            ->assertJsonPath('data.tax_id', 'TME-840215-ABC')
            ->assertJsonPath('data.status', 'active');

        $accountUid = $account->json('data.uid');

        $contact = $this->postJson('/api/contacts', [
            'name' => 'Laura Rios',
            'email' => 'laura.rios@example.test',
            'phone' => '+52 55 8765 4321',
            'company_uid' => $accountUid,
            'job_title' => 'Gerente de Compras',
            'type' => 'person',
            'status' => 'active',
        ]);

        $contact->assertCreated()
            ->assertJsonPath('data.first_name', 'Laura')
            ->assertJsonPath('data.last_name', 'Rios')
            ->assertJsonPath('data.name', 'Laura Rios')
            ->assertJsonPath('data.company_uid', $accountUid)
            ->assertJsonPath('data.company_name', 'TechMex Solutions')
            ->assertJsonPath('data.job_title', 'Gerente de Compras')
            ->assertJsonPath('data.type', 'person')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_contacts_duplicate_check_matches_frontend_contract(): void
    {
        $this->authenticateWithPermissions([
            'accounts.create',
            'contacts.read',
            'contacts.create',
        ]);

        $accountUid = $this->postJson('/api/accounts', [
            'name' => 'Cuenta Duplicados',
            'tax_id' => 'DUP-123',
            'email' => 'dups@example.test',
        ])->assertCreated()->json('data.uid');

        $contactUid = $this->postJson('/api/contacts', [
            'name' => 'Contacto Duplicado',
            'email' => 'duplicado@example.test',
            'company_uid' => $accountUid,
        ])->assertCreated()->json('data.uid');

        $this->postJson('/api/contacts/check-duplicate', [
            'email' => 'duplicado@example.test',
            'tax_id' => 'DUP-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.email_duplicate', true)
            ->assertJsonPath('data.tax_id_duplicate', true);

        $this->postJson('/api/contacts/check-duplicate', [
            'email' => 'duplicado@example.test',
            'tax_id' => 'NO-DUP',
            'exclude_uid' => $contactUid,
        ])
            ->assertOk()
            ->assertJsonPath('data.email_duplicate', false)
            ->assertJsonPath('data.tax_id_duplicate', false);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Contacts',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => str_contains($key, '.') ? explode('.', $key)[0] : 'contacts',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Contacts Owner',
            'email' => 'contacts-owner+' . uniqid() . '@example.test',
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
