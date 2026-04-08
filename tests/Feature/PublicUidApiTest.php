<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\CustomField;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Relation;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\Activity;
use App\Models\Document;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryReservation;
use App\Models\InventoryStock;
use App\Models\Interaction;
use App\Models\PriceBook;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Task;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicUidApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_endpoint_rejects_account_id_payload(): void
    {
        $user = $this->authenticate();
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);

        $response = $this->postJson('/api/contacts', [
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'email' => 'ana@example.com',
            'account_id' => $account->getKey(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonValidationErrors(['account_id']);
    }

    public function test_contacts_endpoint_accepts_account_uid_payload(): void
    {
        $user = $this->authenticate();
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);

        $response = $this->postJson('/api/contacts', [
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'email' => 'ana@example.com',
            'phone' => '+57 3001111111',
            'position' => 'Gerente',
            'account_uid' => $account->uid,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.first_name', 'Ana')
            ->assertJsonPath('data.account_uid', $account->uid)
            ->assertJsonPath('errors', null);

        $this->assertDatabaseHas('contacts', [
            'email' => 'ana@example.com',
            'account_id' => $account->getKey(),
        ]);
    }

    public function test_accounts_index_and_show_do_not_expose_internal_ids(): void
    {
        $user = $this->authenticate();
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);

        $indexResponse = $this->getJson('/api/accounts');
        $indexResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.uid', $account->uid)
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.tenant_id')
            ->assertJsonPath('errors', null);

        $showResponse = $this->getJson("/api/accounts/{$account->uid}");
        $showResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uid', $account->uid)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonPath('errors', null);
    }

    public function test_contacts_index_and_show_do_not_expose_internal_ids(): void
    {
        $user = $this->authenticate();
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);
        $contact = Contact::create([
            'tenant_id' => $user->tenant_id,
            'account_id' => $account->getKey(),
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'email' => 'ana@example.com',
        ]);

        $indexResponse = $this->getJson('/api/contacts');
        $indexResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.uid', $contact->uid)
            ->assertJsonPath('data.0.account_uid', $account->uid)
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.account_id')
            ->assertJsonMissingPath('data.0.tenant_id')
            ->assertJsonPath('errors', null);

        $showResponse = $this->getJson("/api/contacts/{$contact->uid}");
        $showResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uid', $contact->uid)
            ->assertJsonPath('data.account_uid', $account->uid)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.account_id')
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonPath('errors', null);
    }

    public function test_relations_endpoint_rejects_from_id_and_to_id_payloads(): void
    {
        $user = $this->authenticate();
        $from = Contact::create([
            'tenant_id' => $user->tenant_id,
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'email' => 'ana@example.com',
        ]);
        $to = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);

        $response = $this->postJson('/api/relations', [
            'from_type' => 'contact',
            'from_id' => $from->getKey(),
            'to_type' => 'account',
            'to_id' => $to->getKey(),
            'relation_type' => 'works_for',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonValidationErrors(['from_id', 'to_id']);
    }

    public function test_relations_endpoint_accepts_uid_payloads(): void
    {
        $user = $this->authenticate();
        $from = Contact::create([
            'tenant_id' => $user->tenant_id,
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'email' => 'ana@example.com',
        ]);
        $to = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);

        $response = $this->postJson('/api/relations', [
            'from_type' => 'contact',
            'from_uid' => $from->uid,
            'to_type' => 'account',
            'to_uid' => $to->uid,
            'relation_type' => 'works_for',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from_uid', $from->uid)
            ->assertJsonPath('data.to_uid', $to->uid)
            ->assertJsonPath('data.relation_type', 'works_for')
            ->assertJsonPath('errors', null);

        $this->assertDatabaseHas('relations', [
            'from_type' => Contact::class,
            'from_id' => $from->getKey(),
            'to_type' => Account::class,
            'to_id' => $to->getKey(),
            'relation_type' => 'works_for',
        ]);
    }

    public function test_relations_index_and_with_entities_do_not_expose_internal_ids(): void
    {
        $user = $this->authenticate();
        $from = Contact::create([
            'tenant_id' => $user->tenant_id,
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'email' => 'ana@example.com',
        ]);
        $to = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);

        $createResponse = $this->postJson('/api/relations', [
            'from_type' => 'contact',
            'from_uid' => $from->uid,
            'to_type' => 'account',
            'to_uid' => $to->uid,
            'relation_type' => 'works_for',
        ]);

        $relationUid = $createResponse->json('data.uid');

        $indexResponse = $this->getJson('/api/relations');
        $indexResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.uid', $relationUid)
            ->assertJsonPath('data.0.from_uid', $from->uid)
            ->assertJsonPath('data.0.to_uid', $to->uid)
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.from_id')
            ->assertJsonMissingPath('data.0.to_id')
            ->assertJsonMissingPath('data.0.tenant_id')
            ->assertJsonPath('errors', null);

        $withEntitiesResponse = $this->getJson('/api/relations/with-entities');
        $withEntitiesResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.uid', $relationUid)
            ->assertJsonPath('data.0.from_uid', $from->uid)
            ->assertJsonPath('data.0.to_uid', $to->uid)
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.from_id')
            ->assertJsonMissingPath('data.0.to_id')
            ->assertJsonPath('errors', null);
    }

    public function test_plans_index_does_not_expose_internal_ids(): void
    {
        $this->authenticate();

        $plan = Plan::create([
            'name' => 'Pro',
            'price' => 49.99,
            'max_users' => 10,
            'max_accounts' => 100,
            'max_contacts' => 500,
            'max_entities' => 100,
        ]);

        $response = $this->getJson('/api/plans');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.uid', $plan->uid)
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonPath('errors', null);
    }

    public function test_custom_field_store_response_does_not_expose_internal_ids(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/custom-fields', [
            'entity_type' => 'account',
            'name' => 'Region',
            'key' => 'region',
            'type' => 'select',
            'options' => [
                'required' => true,
                'values' => ['Norte', 'Centro', 'Sur'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.entity_type', Account::class)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonPath('errors', null);
    }

    public function test_custom_field_value_endpoint_accepts_entity_uid_and_custom_field_uid(): void
    {
        $user = $this->authenticate();
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);
        $field = CustomField::create([
            'tenant_id' => $user->tenant_id,
            'entity_type' => Account::class,
            'name' => 'Region',
            'key' => 'region',
            'type' => 'select',
            'options' => [
                'required' => true,
                'values' => ['Norte', 'Centro', 'Sur'],
            ],
        ]);

        $response = $this->postJson('/api/custom-fields/value', [
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'custom_field_uid' => $field->uid,
            'value' => 'Norte',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.entity_uid', $account->uid)
            ->assertJsonPath('data.custom_field_uid', $field->uid)
            ->assertJsonPath('data.value', 'Norte')
            ->assertJsonPath('errors', null);

        $this->assertDatabaseHas('custom_field_values', [
            'entity_type' => Account::class,
            'entity_id' => $account->getKey(),
            'custom_field_id' => $field->getKey(),
        ]);
    }

    public function test_custom_field_value_endpoint_rejects_missing_uid_contract(): void
    {
        $user = $this->authenticate();
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);
        $field = CustomField::create([
            'tenant_id' => $user->tenant_id,
            'entity_type' => Account::class,
            'name' => 'Region',
            'key' => 'region',
            'type' => 'text',
        ]);

        $response = $this->postJson('/api/custom-fields/value', [
            'entity_type' => 'account',
            'entity_id' => $account->getKey(),
            'custom_field_id' => $field->getKey(),
            'value' => 'Norte',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonValidationErrors(['entity_uid', 'custom_field_uid']);
    }

    public function test_login_response_exposes_uid_and_not_internal_ids(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecret();

        $user = User::create([
            'name' => 'Admin Demo',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'tenant_id' => $tenant->getKey(),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'two_factor_code' => $twoFactorService->currentCode($secret),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.uid', $user->uid)
            ->assertJsonPath('data.user.tenant_uid', $tenant->uid)
            ->assertJsonMissingPath('data.user.id')
            ->assertJsonMissingPath('data.user.tenant_id')
            ->assertJsonPath('errors', null);
    }

    public function test_login_requires_two_factor_setup_when_user_is_not_enrolled(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Pending 2FA',
            'email' => 'pending2fa@example.com',
            'password' => Hash::make('secret123'),
            'tenant_id' => $tenant->getKey(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'pending2fa@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Debes configurar 2FA antes de acceder')
            ->assertJsonPath('data.requires_two_factor_setup', true)
            ->assertJsonPath('data.user.uid', $user->uid)
            ->assertJsonMissingPath('data.user.id')
            ->assertJsonPath('errors', null);
    }

    public function test_me_response_exposes_uid_and_not_internal_ids(): void
    {
        $user = $this->authenticate();

        $response = $this->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uid', $user->uid)
            ->assertJsonPath('data.tenant_uid', $user->tenant->uid)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonPath('errors', null);
    }

    public function test_accounts_index_requires_accounts_read_permission(): void
    {
        $this->authenticate([]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: accounts.read');
    }

    public function test_contacts_store_requires_contacts_create_permission(): void
    {
        $user = $this->authenticate(['accounts.read']);
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme SAS',
            'document' => '900123456',
            'email' => 'acme@example.com',
        ]);

        $response = $this->postJson('/api/contacts', [
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'email' => 'ana@example.com',
            'account_uid' => $account->uid,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: contacts.create');
    }

    public function test_users_index_requires_users_manage_permission(): void
    {
        $this->authenticate([]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: users.manage');
    }

    public function test_relations_index_requires_relations_read_permission(): void
    {
        $this->authenticate([]);

        $response = $this->getJson('/api/relations');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: relations.read');
    }

    public function test_custom_fields_store_requires_custom_fields_manage_permission(): void
    {
        $this->authenticate([]);

        $response = $this->postJson('/api/custom-fields', [
            'entity_type' => 'account',
            'name' => 'Region',
            'key' => 'region',
            'type' => 'text',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: custom-fields.manage');
    }

    public function test_crm_entities_index_requires_crm_entities_read_permission(): void
    {
        $this->authenticate([]);

        $response = $this->getJson('/api/crm-entities');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: crm-entities.read');
    }

    public function test_logs_index_requires_logs_read_permission(): void
    {
        $this->authenticate([]);

        $response = $this->getJson('/api/logs');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: logs.read');
    }

    public function test_metrics_endpoint_requires_metrics_read_permission(): void
    {
        $this->authenticate([]);

        $response = $this->getJson('/api/metrics/my-usage');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: metrics.read');
    }

    public function test_seeded_owner_role_grants_permissions_via_role_assignment(): void
    {
        $user = $this->authenticate([]);

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $ownerRole = Role::query()->where('key', 'owner')->firstOrFail();
        $user->assignRole($ownerRole);

        $response = $this->getJson('/api/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('errors', null);
    }

    public function test_rbac_roles_endpoint_lists_seeded_roles_for_tenant(): void
    {
        $this->authenticate();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $response = $this->getJson('/api/rbac/roles');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['key' => 'owner'])
            ->assertJsonFragment(['key' => 'manager'])
            ->assertJsonFragment(['key' => 'seller'])
            ->assertJsonPath('errors', null);
    }

    public function test_rbac_roles_endpoint_requires_users_manage_permission(): void
    {
        $this->authenticate([]);

        $response = $this->getJson('/api/rbac/roles');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado')
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: users.manage');
    }

    public function test_can_assign_role_to_user_and_view_effective_permissions(): void
    {
        $admin = $this->authenticate(['users.manage']);
        $targetUser = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
        ]);

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $sellerRole = Role::query()->where('key', 'seller')->firstOrFail();

        $assignResponse = $this->postJson("/api/users/{$targetUser->uid}/roles", [
            'role_uid' => $sellerRole->uid,
        ]);

        $assignResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Rol asignado')
            ->assertJsonFragment(['key' => 'seller'])
            ->assertJsonFragment(['key' => 'accounts.read']);

        $accessResponse = $this->getJson("/api/users/{$targetUser->uid}/access");

        $accessResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.uid', $targetUser->uid)
            ->assertJsonFragment(['key' => 'seller'])
            ->assertJsonFragment(['key' => 'contacts.create'])
            ->assertJsonPath('errors', null);
    }

    public function test_can_grant_and_revoke_direct_permission_from_user(): void
    {
        $admin = $this->authenticate(['users.manage']);
        $targetUser = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['key' => 'logs.read'],
            [
                'module' => 'logs',
                'action' => 'read',
                'description' => 'Ver logs del tenant',
            ]
        );

        $grantResponse = $this->postJson("/api/users/{$targetUser->uid}/permissions", [
            'permission_uid' => $permission->uid,
        ]);

        $grantResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Permiso directo asignado')
            ->assertJsonFragment(['key' => 'logs.read']);

        $revokeResponse = $this->deleteJson("/api/users/{$targetUser->uid}/permissions/{$permission->uid}");

        $revokeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Permiso directo retirado')
            ->assertJsonMissing([
                'key' => 'logs.read',
            ]);
    }

    public function test_seller_only_sees_owned_accounts(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $sellerA = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $sellerB = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->grantPermissions($sellerA, ['accounts.read']);
        $this->grantPermissions($sellerB, ['accounts.read']);

        $this->actingAsApiUser($sellerA, $tenant);
        $ownedAccount = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta A',
            'document' => '900100001',
        ]);

        $this->actingAsApiUser($sellerB, $tenant);
        $hiddenAccount = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta B',
            'document' => '900100002',
        ]);

        $this->actingAsApiUser($sellerA, $tenant);

        $response = $this->getJson('/api/accounts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uid', $ownedAccount->uid)
            ->assertJsonMissing(['uid' => $hiddenAccount->uid]);
    }

    public function test_manager_sees_accounts_from_subordinates(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $manager = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $seller = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'manager_id' => $manager->getKey(),
        ]);

        $this->grantPermissions($manager, ['accounts.read']);
        $this->grantPermissions($seller, ['accounts.read']);

        $this->actingAsApiUser($seller, $tenant);
        $teamAccount = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta Equipo',
            'document' => '900100003',
        ]);

        $this->actingAsApiUser($manager, $tenant);

        $response = $this->getJson('/api/accounts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['uid' => $teamAccount->uid]);
    }

    public function test_owner_role_bypasses_row_level_scope_for_accounts(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $owner = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $sellerA = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $sellerB = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $ownerRole = Role::query()->where('key', 'owner')->firstOrFail();
        $owner->assignRole($ownerRole);

        $this->grantPermissions($sellerA, ['accounts.read']);
        $this->grantPermissions($sellerB, ['accounts.read']);

        $this->actingAsApiUser($sellerA, $tenant);
        $accountA = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta A',
            'document' => '900100004',
        ]);

        $this->actingAsApiUser($sellerB, $tenant);
        $accountB = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta B',
            'document' => '900100005',
        ]);

        $this->actingAsApiUser($owner, $tenant);

        $response = $this->getJson('/api/accounts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['uid' => $accountA->uid])
            ->assertJsonFragment(['uid' => $accountB->uid]);
    }

    public function test_seller_only_sees_contacts_from_owned_portfolio(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $sellerA = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $sellerB = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->grantPermissions($sellerA, ['contacts.read']);
        $this->grantPermissions($sellerB, ['contacts.read']);

        $this->actingAsApiUser($sellerA, $tenant);
        $accountA = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta A',
            'document' => '900100006',
        ]);
        $visibleContact = Contact::create([
            'tenant_id' => $tenant->getKey(),
            'account_id' => $accountA->getKey(),
            'first_name' => 'Ana',
            'email' => 'ana.visible@example.com',
        ]);

        $this->actingAsApiUser($sellerB, $tenant);
        $accountB = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta B',
            'document' => '900100007',
        ]);
        $hiddenContact = Contact::create([
            'tenant_id' => $tenant->getKey(),
            'account_id' => $accountB->getKey(),
            'first_name' => 'Bruno',
            'email' => 'bruno.hidden@example.com',
        ]);

        $this->actingAsApiUser($sellerA, $tenant);

        $response = $this->getJson('/api/contacts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['uid' => $visibleContact->uid])
            ->assertJsonMissing(['uid' => $hiddenContact->uid]);
    }

    public function test_seller_only_sees_relations_between_visible_entities(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $sellerA = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $sellerB = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->grantPermissions($sellerA, ['relations.read']);

        $this->actingAsApiUser($sellerA, $tenant);
        $visibleAccount = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta Visible',
            'document' => '900100008',
        ]);
        $visibleContact = Contact::create([
            'tenant_id' => $tenant->getKey(),
            'account_id' => $visibleAccount->getKey(),
            'first_name' => 'Ana',
            'email' => 'ana.relation@example.com',
        ]);

        $this->actingAsApiUser($sellerB, $tenant);
        $hiddenAccount = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta Oculta',
            'document' => '900100009',
        ]);
        $hiddenContact = Contact::create([
            'tenant_id' => $tenant->getKey(),
            'account_id' => $hiddenAccount->getKey(),
            'first_name' => 'Bruno',
            'email' => 'bruno.relation@example.com',
        ]);

        $visibleRelation = Relation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'from_type' => Contact::class,
            'from_id' => $visibleContact->getKey(),
            'to_type' => Account::class,
            'to_id' => $visibleAccount->getKey(),
            'relation_type' => 'works_for',
        ]);

        $hiddenRelation = Relation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'from_type' => Contact::class,
            'from_id' => $hiddenContact->getKey(),
            'to_type' => Account::class,
            'to_id' => $hiddenAccount->getKey(),
            'relation_type' => 'works_for',
        ]);

        $crossPortfolioRelation = Relation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'from_type' => Contact::class,
            'from_id' => $visibleContact->getKey(),
            'to_type' => Account::class,
            'to_id' => $hiddenAccount->getKey(),
            'relation_type' => 'influences',
        ]);

        $this->actingAsApiUser($sellerA, $tenant);

        $response = $this->getJson('/api/relations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['uid' => $visibleRelation->uid])
            ->assertJsonMissing(['uid' => $hiddenRelation->uid])
            ->assertJsonMissing(['uid' => $crossPortfolioRelation->uid]);
    }

    public function test_relations_with_entities_only_returns_visible_graph_data(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $sellerA = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $sellerB = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->grantPermissions($sellerA, ['relations.read']);

        $this->actingAsApiUser($sellerA, $tenant);
        $visibleAccount = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta Visible',
            'document' => '900100010',
        ]);
        $visibleContact = Contact::create([
            'tenant_id' => $tenant->getKey(),
            'account_id' => $visibleAccount->getKey(),
            'first_name' => 'Ana',
            'last_name' => 'Visible',
            'email' => 'ana.graph@example.com',
        ]);

        $this->actingAsApiUser($sellerB, $tenant);
        $hiddenAccount = Account::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cuenta Oculta',
            'document' => '900100011',
        ]);

        $visibleRelation = Relation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'from_type' => Contact::class,
            'from_id' => $visibleContact->getKey(),
            'to_type' => Account::class,
            'to_id' => $visibleAccount->getKey(),
            'relation_type' => 'works_for',
        ]);

        $crossPortfolioRelation = Relation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'from_type' => Contact::class,
            'from_id' => $visibleContact->getKey(),
            'to_type' => Account::class,
            'to_id' => $hiddenAccount->getKey(),
            'relation_type' => 'influences',
        ]);

        $this->actingAsApiUser($sellerA, $tenant);

        $response = $this->getJson('/api/relations/with-entities');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['uid' => $visibleRelation->uid])
            ->assertJsonFragment(['from' => 'Ana Visible'])
            ->assertJsonFragment(['to' => 'Cuenta Visible'])
            ->assertJsonMissing(['uid' => $crossPortfolioRelation->uid])
            ->assertJsonMissing(['to' => 'Cuenta Oculta']);
    }

    public function test_relation_hierarchy_and_entity_views_do_not_leak_hidden_entities(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $sellerA = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $sellerB = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->grantPermissions($sellerA, ['relations.read']);

        $this->actingAsApiUser($sellerA, $tenant);
        $visibleContact = Contact::create([
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Ana',
            'last_name' => 'Visible',
            'email' => 'ana.hierarchy@example.com',
        ]);

        $this->actingAsApiUser($sellerB, $tenant);
        $hiddenContact = Contact::create([
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Bruno',
            'last_name' => 'Oculto',
            'email' => 'bruno.hierarchy@example.com',
        ]);

        Relation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'from_type' => Contact::class,
            'from_id' => $visibleContact->getKey(),
            'to_type' => Contact::class,
            'to_id' => $hiddenContact->getKey(),
            'relation_type' => 'reports_to',
        ]);

        $this->actingAsApiUser($sellerA, $tenant);

        $visibleResponse = $this->getJson("/api/relations/contact/{$visibleContact->uid}");
        $visibleResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        $hierarchyResponse = $this->getJson("/api/relations/hierarchy/contact/{$visibleContact->uid}");
        $hierarchyResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        $hiddenResponse = $this->getJson("/api/relations/contact/{$hiddenContact->uid}");
        $hiddenResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error');
    }

    public function test_inactive_tenant_blocks_full_access_routes(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Inactivo',
            'is_active' => false,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
        ]);

        $this->grantPermissions($user, ['accounts.read']);
        $this->actingAsApiUser($user, $tenant);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cuenta suspendida o vencida')
            ->assertJsonPath('errors.tenant.0', 'Cuenta suspendida o vencida');
    }

    public function test_can_create_update_and_delete_custom_role(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $admin = $this->authenticate(['users.manage']);

        $accountsRead = Permission::query()->where('key', 'accounts.read')->firstOrFail();
        $contactsRead = Permission::query()->where('key', 'contacts.read')->firstOrFail();

        $createResponse = $this->postJson('/api/rbac/roles', [
            'name' => 'Analista',
            'key' => 'analyst',
            'description' => 'Rol personalizado',
            'permission_uids' => [$accountsRead->uid],
        ]);

        $roleUid = $createResponse->json('data.uid');

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Rol creado')
            ->assertJsonPath('data.key', 'analyst')
            ->assertJsonFragment(['key' => 'accounts.read']);

        $updateResponse = $this->putJson("/api/rbac/roles/{$roleUid}", [
            'name' => 'Analista Senior',
            'permission_uids' => [$accountsRead->uid, $contactsRead->uid],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Rol actualizado')
            ->assertJsonPath('data.name', 'Analista Senior')
            ->assertJsonFragment(['key' => 'contacts.read']);

        $deleteResponse = $this->deleteJson("/api/rbac/roles/{$roleUid}");

        $deleteResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Rol eliminado');
    }

    public function test_system_roles_cannot_be_updated_or_deleted(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $admin = $this->authenticate(['users.manage']);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $ownerRole = Role::query()->where('key', 'owner')->firstOrFail();

        $updateResponse = $this->putJson("/api/rbac/roles/{$ownerRole->uid}", [
            'name' => 'Nuevo Owner',
        ]);

        $updateResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonPath('errors.role_uid.0', 'Los roles del sistema no se pueden editar');

        $deleteResponse = $this->deleteJson("/api/rbac/roles/{$ownerRole->uid}");

        $deleteResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonPath('errors.role_uid.0', 'Los roles del sistema no se pueden eliminar');
    }

    public function test_seller_only_sees_owned_crm_entities(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $sellerA = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $sellerB = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->grantPermissions($sellerA, ['crm-entities.read']);
        $this->grantPermissions($sellerB, ['crm-entities.read']);

        $this->actingAsApiUser($sellerA, $tenant);
        $visibleEntity = \App\Models\CrmEntity::create([
            'tenant_id' => $tenant->getKey(),
            'type' => 'B2B',
            'profile_data' => [
                'company_name' => 'Visible Corp',
                'document' => '900200001',
            ],
        ]);

        $this->actingAsApiUser($sellerB, $tenant);
        $hiddenEntity = \App\Models\CrmEntity::create([
            'tenant_id' => $tenant->getKey(),
            'type' => 'B2B',
            'profile_data' => [
                'company_name' => 'Hidden Corp',
                'document' => '900200002',
            ],
        ]);

        $this->actingAsApiUser($sellerA, $tenant);

        $response = $this->getJson('/api/crm-entities');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['uid' => $visibleEntity->uid])
            ->assertJsonMissing(['uid' => $hiddenEntity->uid]);
    }

    public function test_can_create_assign_and_remove_tags(): void
    {
        $user = $this->authenticate(['tags.manage', 'accounts.read']);
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Acme VIP',
            'document' => '900300001',
        ]);

        $createResponse = $this->postJson('/api/tags', [
            'name' => 'VIP',
            'key' => 'vip',
            'color' => '#FFD700',
            'category' => 'segment',
        ]);

        $tagUid = $createResponse->json('data.uid');

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Etiqueta creada')
            ->assertJsonPath('data.key', 'vip');

        $assignResponse = $this->postJson('/api/tags/assign', [
            'tag_uid' => $tagUid,
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
        ]);

        $assignResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Etiqueta asignada')
            ->assertJsonPath('data.entity_uid', $account->uid);

        $this->assertDatabaseHas('taggables', [
            'tag_id' => Tag::query()->where('uid', $tagUid)->value('id'),
            'taggable_id' => $account->getKey(),
            'taggable_type' => Account::class,
        ]);

        $removeResponse = $this->postJson('/api/tags/unassign', [
            'tag_uid' => $tagUid,
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
        ]);

        $removeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Etiqueta retirada');
    }

    public function test_search_endpoint_filters_by_type_tag_and_date(): void
    {
        $user = $this->authenticate(['search.use', 'tags.manage']);
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cliente VIP',
            'document' => '900300002',
            'email' => 'vip@example.com',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $contact = Contact::create([
            'tenant_id' => $user->tenant_id,
            'first_name' => 'Otro',
            'last_name' => 'Cliente',
            'email' => 'other@example.com',
        ]);

        $tag = Tag::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'VIP',
            'key' => 'vip',
            'color' => '#FFD700',
            'category' => 'segment',
        ]);

        $account->tags()->attach($tag->getKey());

        $response = $this->postJson('/api/search', [
            'entity_types' => ['accounts'],
            'query' => 'VIP',
            'tag_uids' => [$tag->uid],
            'created_from' => now()->subDays(2)->toDateString(),
            'created_to' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.accounts', 1)
            ->assertJsonPath('data.totals.contacts', 0)
            ->assertJsonPath('data.total', 1)
            ->assertJsonFragment(['uid' => $account->uid])
            ->assertJsonMissing(['uid' => $contact->uid]);
    }

    public function test_search_endpoint_supports_pagination_and_sorting(): void
    {
        $user = $this->authenticate(['search.use']);

        Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Zulu Corp',
            'document' => '900300010',
        ]);

        Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Alpha Corp',
            'document' => '900300011',
        ]);

        Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Beta Corp',
            'document' => '900300012',
        ]);

        $response = $this->postJson('/api/search', [
            'entity_types' => ['accounts'],
            'sort_by' => 'name',
            'sort_direction' => 'asc',
            'page' => 1,
            'per_page' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.page', 1)
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonPath('data.meta.accounts.total', 3)
            ->assertJsonPath('data.results.accounts.0.name', 'Alpha Corp')
            ->assertJsonPath('data.results.accounts.1.name', 'Beta Corp');
    }

    public function test_search_endpoint_filters_by_custom_field_value(): void
    {
        $user = $this->authenticate(['search.use', 'custom-fields.manage']);

        $northAccount = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'North Corp',
            'document' => '900300020',
        ]);

        $southAccount = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'South Corp',
            'document' => '900300021',
        ]);

        $field = CustomField::create([
            'tenant_id' => $user->tenant_id,
            'entity_type' => Account::class,
            'name' => 'Region',
            'key' => 'region',
            'type' => 'text',
        ]);

        $this->postJson('/api/custom-fields/value', [
            'entity_type' => 'account',
            'entity_uid' => $northAccount->uid,
            'custom_field_uid' => $field->uid,
            'value' => 'North',
        ])->assertOk();

        $this->postJson('/api/custom-fields/value', [
            'entity_type' => 'account',
            'entity_uid' => $southAccount->uid,
            'custom_field_uid' => $field->uid,
            'value' => 'South',
        ])->assertOk();

        $response = $this->postJson('/api/search', [
            'entity_types' => ['accounts'],
            'custom_field_filters' => [
                [
                    'custom_field_uid' => $field->uid,
                    'value' => 'North',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.accounts', 1)
            ->assertJsonFragment(['uid' => $northAccount->uid])
            ->assertJsonMissing(['uid' => $southAccount->uid]);
    }

    public function test_dashboard_core_returns_operational_metrics(): void
    {
        $user = $this->authenticate(['dashboard.read', 'tags.manage']);

        Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cuenta Dashboard',
            'document' => '900300003',
        ]);

        Contact::create([
            'tenant_id' => $user->tenant_id,
            'first_name' => 'Ana',
            'email' => 'dashboard@example.com',
        ]);

        CrmEntity::create([
            'tenant_id' => $user->tenant_id,
            'type' => 'B2B',
            'profile_data' => [
                'company_name' => 'Dashboard Corp',
            ],
        ]);

        Tag::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Pipeline',
            'key' => 'pipeline',
            'color' => '#00AAFF',
            'category' => 'pipeline',
        ]);

        $response = $this->getJson('/api/dashboard/core');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.tasks_supported', true)
            ->assertJsonPath('data.summary.overdue_tasks_today', 0)
            ->assertJsonPath('data.breakdown.accounts_created_today', 1)
            ->assertJsonPath('data.breakdown.contacts_created_today', 1)
            ->assertJsonPath('data.breakdown.crm_entities_created_today', 1)
            ->assertJsonPath('data.breakdown.tasks_due_today', 0)
            ->assertJsonPath('data.totals.tags', 1)
            ->assertJsonPath('data.totals.tasks', 0);
    }

    public function test_tags_and_search_routes_require_permissions(): void
    {
        $this->authenticate([]);

        $tagsResponse = $this->getJson('/api/tags');
        $searchResponse = $this->postJson('/api/search', []);
        $exportResponse = $this->postJson('/api/search/export', []);
        $dashboardResponse = $this->getJson('/api/dashboard/core');

        $tagsResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: tags.manage');

        $searchResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: search.use');

        $exportResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: search.use');

        $dashboardResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: dashboard.read');
    }

    public function test_search_export_returns_filtered_json_payload(): void
    {
        $user = $this->authenticate(['search.use', 'tags.manage']);

        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Export VIP',
            'document' => '900300030',
        ]);

        $tag = Tag::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Exportable',
            'key' => 'exportable',
            'color' => '#008000',
            'category' => 'segment',
        ]);

        $account->tags()->attach($tag->getKey());

        $response = $this->postJson('/api/search/export', [
            'format' => 'json',
            'entity_types' => ['accounts'],
            'tag_uids' => [$tag->uid],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.accounts', 1)
            ->assertJsonPath('data.total', 1)
            ->assertJsonFragment(['uid' => $account->uid]);
    }

    public function test_search_export_returns_csv_download(): void
    {
        $user = $this->authenticate(['search.use']);

        Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'CSV Corp',
            'document' => '900300031',
        ]);

        $response = $this->post('/api/search/export', [
            'format' => 'csv',
            'entity_types' => ['accounts'],
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('entity_type,uid,display_name,owner_user_uid,tags,custom_fields,payload', $response->streamedContent());
        $this->assertStringContainsString('account', $response->streamedContent());
        $this->assertStringContainsString('CSV Corp', $response->streamedContent());
    }

    public function test_tasks_crud_and_dashboard_overdue_metric_work(): void
    {
        $user = $this->authenticate(['tasks.create', 'tasks.update', 'tasks.read', 'dashboard.read']);

        $createResponse = $this->postJson('/api/tasks', [
            'title' => 'Llamar cliente',
            'description' => 'Seguimiento comercial',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => today()->toDateString(),
        ]);

        $taskUid = $createResponse->json('data.uid');

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tarea creada')
            ->assertJsonPath('data.title', 'Llamar cliente');

        $indexResponse = $this->getJson('/api/tasks');
        $indexResponse->assertOk()
            ->assertJsonFragment(['uid' => $taskUid]);

        $dashboardResponse = $this->getJson('/api/dashboard/core');
        $dashboardResponse->assertOk()
            ->assertJsonPath('data.summary.tasks_supported', true)
            ->assertJsonPath('data.summary.overdue_tasks_today', 1)
            ->assertJsonPath('data.breakdown.tasks_due_today', 1)
            ->assertJsonPath('data.totals.tasks', 1);

        $updateResponse = $this->putJson("/api/tasks/{$taskUid}", [
            'status' => 'completed',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tarea actualizada')
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_tasks_routes_require_permissions(): void
    {
        $this->authenticate([]);

        $indexResponse = $this->getJson('/api/tasks');
        $storeResponse = $this->postJson('/api/tasks', [
            'title' => 'Sin permiso',
        ]);

        $indexResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: tasks.read');

        $storeResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: tasks.create');
    }

    public function test_can_register_interactions_and_view_timeline(): void
    {
        $user = $this->authenticate(['interactions.create', 'interactions.read']);
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cliente Timeline',
            'document' => '900400001',
        ]);

        $this->postJson('/api/interactions/notes', [
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'subject' => 'Nota inicial',
            'content' => 'Cliente interesado en propuesta',
        ])->assertCreated();

        $this->postJson('/api/interactions/calls', [
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'subject' => 'Llamada seguimiento',
            'content' => 'Se agendo reunion',
            'meta' => ['duration_seconds' => 180],
        ])->assertCreated();

        $timelineResponse = $this->getJson("/api/interactions/account/{$account->uid}");

        $timelineResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['type' => 'note'])
            ->assertJsonFragment(['type' => 'call']);
    }

    public function test_activity_status_changes_are_audited_in_timeline(): void
    {
        $user = $this->authenticate(['activities.create', 'activities.update', 'interactions.read']);
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cliente Auditoria',
            'document' => '900400002',
        ]);

        $activityResponse = $this->postJson('/api/activities', [
            'type' => 'meeting',
            'title' => 'Demo comercial',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
        ]);

        $activityUid = $activityResponse->json('data.uid');

        $this->putJson("/api/activities/{$activityUid}", [
            'status' => 'completed',
        ])->assertOk();

        $timelineResponse = $this->getJson("/api/interactions/account/{$account->uid}");

        $timelineResponse->assertOk()
            ->assertJsonFragment(['type' => 'status_change'])
            ->assertJsonFragment(['subject' => 'Cambio de estado']);
    }

    public function test_activities_can_be_queried_by_date_range_and_become_overdue(): void
    {
        $user = $this->authenticate(['activities.create', 'activities.read']);

        $this->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Actividad vencida',
            'scheduled_at' => now()->subDay()->toIso8601String(),
        ])->assertCreated();

        $response = $this->getJson('/api/activities/range?from=' . now()->subDays(2)->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['status' => 'overdue']);
    }

    public function test_can_upload_list_and_download_pdf_documents(): void
    {
        Storage::fake('local');

        $user = $this->authenticate(['documents.create', 'documents.read']);
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cliente Docs',
            'document' => '900400003',
        ]);

        $uploadResponse = $this->postJson('/api/documents', [
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'file' => UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf'),
        ]);

        $documentUid = $uploadResponse->json('data.uid');

        $uploadResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Documento subido')
            ->assertJsonPath('data.original_name', 'contrato.pdf');

        $listResponse = $this->getJson("/api/documents/entity/account/{$account->uid}");
        $listResponse->assertOk()
            ->assertJsonFragment(['uid' => $documentUid]);

        $downloadResponse = $this->get("/api/documents/download/{$documentUid}");
        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('content-type', 'application/pdf');
    }

    public function test_document_upload_rejects_non_pdf_files(): void
    {
        Storage::fake('local');

        $user = $this->authenticate(['documents.create']);
        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cliente Docs',
            'document' => '900400004',
        ]);

        $response = $this->postJson('/api/documents', [
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'file' => UploadedFile::fake()->create('foto.png', 50, 'image/png'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonPath('errors.file.0', 'Solo se permiten archivos PDF');
    }

    public function test_interactions_activities_and_documents_require_permissions(): void
    {
        $this->authenticate([]);

        $timelineResponse = $this->getJson('/api/interactions/account/00000000-0000-0000-0000-000000000000');
        $activityResponse = $this->getJson('/api/activities');
        $documentResponse = $this->postJson('/api/documents', []);

        $timelineResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: interactions.read');

        $activityResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: activities.read');

        $documentResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: documents.create');
    }

    public function test_inventory_master_view_returns_aggregated_stock_and_filters(): void
    {
        $user = $this->authenticate(['inventory.read', 'inventory.manage', 'inventory.reserve', 'inventory.report']);

        $category = InventoryCategory::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Hardware',
            'key' => 'hardware',
        ]);

        $mainWarehouse = Warehouse::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Principal',
            'code' => 'BOD-01',
        ]);

        $secondaryWarehouse = Warehouse::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Secundaria',
            'code' => 'BOD-02',
        ]);

        $product = InventoryProduct::create([
            'tenant_id' => $user->tenant_id,
            'category_id' => $category->getKey(),
            'sku' => 'SKU-001',
            'name' => 'Router Empresarial',
            'reorder_point' => 5,
        ]);

        InventoryStock::create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $product->getKey(),
            'warehouse_id' => $mainWarehouse->getKey(),
            'physical_stock' => 8,
            'reserved_stock' => 3,
        ]);

        InventoryStock::create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $product->getKey(),
            'warehouse_id' => $secondaryWarehouse->getKey(),
            'physical_stock' => 4,
            'reserved_stock' => 0,
        ]);

        $response = $this->getJson('/api/inventory/master?category_uid=' . $category->uid . '&stock_state=normal');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.uid', $product->uid)
            ->assertJsonPath('data.data.0.sku', 'SKU-001')
            ->assertJsonPath('data.data.0.stock_physical_total', 12)
            ->assertJsonPath('data.data.0.stock_reserved_total', 3)
            ->assertJsonPath('data.data.0.stock_available_total', 9)
            ->assertJsonPath('data.data.0.stock_indicator', 'green');
    }

    public function test_inventory_reservation_flow_reserves_and_rejects_excess_stock(): void
    {
        $user = $this->authenticate(['inventory.read', 'inventory.manage', 'inventory.reserve', 'inventory.report']);

        $warehouse = Warehouse::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Principal',
            'code' => 'BOD-03',
        ]);

        $product = InventoryProduct::create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-002',
            'name' => 'Switch 24 Puertos',
            'reorder_point' => 2,
        ]);

        InventoryStock::create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'physical_stock' => 10,
            'reserved_stock' => 1,
        ]);

        $reserveResponse = $this->postJson('/api/inventory/reservations', [
            'product_uid' => $product->uid,
            'warehouse_uid' => $warehouse->uid,
            'quantity' => 4,
            'source_type' => 'quotation',
            'source_uid' => 'cotizacion-b2b-001',
            'comment' => 'Reserva comercial',
        ]);

        $reservationUid = $reserveResponse->json('data.reservation.uid');

        $reserveResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.preview.stock_actual', 10)
            ->assertJsonPath('data.preview.stock_reservado_actual', 5)
            ->assertJsonPath('data.preview.stock_disponible', 5)
            ->assertJsonPath('data.preview.unidades_a_reservar', 4);

        $sourceResponse = $this->getJson('/api/inventory/reservations/source/quotation/cotizacion-b2b-001');
        $sourceResponse->assertOk()
            ->assertJsonPath('data.totals.reserved_units', 4)
            ->assertJsonFragment(['uid' => $reservationUid]);

        $errorResponse = $this->postJson('/api/inventory/reservations', [
            'product_uid' => $product->uid,
            'warehouse_uid' => $warehouse->uid,
            'quantity' => 6,
            'source_type' => 'quotation',
            'source_uid' => 'cotizacion-b2b-002',
        ]);

        $errorResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.quantity.0', 'La reserva excede el stock disponible');

        $releaseResponse = $this->deleteJson("/api/inventory/reservations/{$reservationUid}");
        $releaseResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reservation.status', 'released');
    }

    public function test_inventory_transfer_updates_multi_warehouse_stock(): void
    {
        $user = $this->authenticate(['inventory.read', 'inventory.manage', 'inventory.reserve', 'inventory.report']);

        $origin = Warehouse::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Origen',
            'code' => 'BOD-04',
        ]);

        $destination = Warehouse::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Destino',
            'code' => 'BOD-05',
        ]);

        $product = InventoryProduct::create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-003',
            'name' => 'Access Point',
            'reorder_point' => 3,
        ]);

        InventoryStock::create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $product->getKey(),
            'warehouse_id' => $origin->getKey(),
            'physical_stock' => 9,
            'reserved_stock' => 2,
        ]);

        InventoryStock::create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $product->getKey(),
            'warehouse_id' => $destination->getKey(),
            'physical_stock' => 1,
            'reserved_stock' => 0,
        ]);

        $response = $this->postJson('/api/inventory/movements/transfer', [
            'product_uid' => $product->uid,
            'from_warehouse_uid' => $origin->uid,
            'to_warehouse_uid' => $destination->uid,
            'quantity' => 4,
            'comment' => 'Rebalanceo',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.preview.from.projected_physical_stock', 5)
            ->assertJsonPath('data.preview.to.projected_physical_stock', 5);

        $warehouseResponse = $this->getJson("/api/inventory/warehouses/{$destination->uid}/stocks");
        $warehouseResponse->assertOk()
            ->assertJsonPath('data.data.0.stock_physical_total', 5);
    }

    public function test_inventory_report_and_permissions_work(): void
    {
        $this->authenticate([]);

        $masterResponse = $this->getJson('/api/inventory/master');
        $reportResponse = $this->getJson('/api/inventory/report');

        $masterResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: inventory.read');

        $reportResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: inventory.report');

        $user = $this->authenticate(['inventory.read', 'inventory.manage', 'inventory.reserve', 'inventory.report']);
        $category = InventoryCategory::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Licencias',
            'key' => 'licenses',
        ]);
        $warehouse = Warehouse::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Central',
            'code' => 'BOD-06',
        ]);
        $critical = InventoryProduct::create([
            'tenant_id' => $user->tenant_id,
            'category_id' => $category->getKey(),
            'sku' => 'SKU-004',
            'name' => 'Firewall',
            'reorder_point' => 5,
        ]);
        InventoryStock::create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $critical->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'physical_stock' => 3,
            'reserved_stock' => 0,
        ]);

        $jsonReport = $this->getJson('/api/inventory/report');
        $jsonReport->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary_by_category.0.category', 'Licencias')
            ->assertJsonPath('data.rupture_risk.low_stock_count', 1);

        $csvReport = $this->get('/api/inventory/report/export');
        $csvReport->assertOk();
        $csvReport->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('sku,product,category,warehouse_uid', $csvReport->getContent());
    }

    public function test_quotation_item_can_reserve_stock_and_expose_reserved_indicator(): void
    {
        $user = $this->authenticate([
            'inventory.read',
            'inventory.manage',
            'inventory.reserve',
            'inventory.report',
            'quotations.read',
            'quotations.create',
            'quotations.update',
            'accounts.read',
        ]);

        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cliente Cotizacion',
            'document' => '901000001',
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'CPQ Principal',
            'code' => 'CPQ-01',
        ]);

        $product = InventoryProduct::create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-CPQ-01',
            'name' => 'Servidor Rack',
            'reorder_point' => 1,
        ]);

        InventoryStock::create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'physical_stock' => 12,
            'reserved_stock' => 2,
        ]);

        $quotationResponse = $this->postJson('/api/quotations', [
            'quote_number' => 'COT-0001',
            'title' => 'Cotizacion B2B Acme',
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'status' => 'draft',
        ]);

        $quotationUid = $quotationResponse->json('data.uid');

        $quotationResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quoteable_uid', $account->uid);

        $itemResponse = $this->postJson("/api/quotations/{$quotationUid}/items", [
            'product_uid' => $product->uid,
            'warehouse_uid' => $warehouse->uid,
            'description' => 'Servidor para proyecto B2B',
            'quantity' => 5,
            'unit_price' => 1500,
        ]);

        $itemUid = $itemResponse->json('data.uid');

        $itemResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_uid', $product->uid)
            ->assertJsonPath('data.warehouse_uid', $warehouse->uid)
            ->assertJsonPath('data.reservation_indicator', 'not_reserved');

        $reserveResponse = $this->postJson("/api/quotations/items/{$itemUid}/reserve-stock", [
            'quantity' => 3,
            'comment' => 'Reserva desde CPQ',
        ]);

        $reservationUid = $reserveResponse->json('data.reservation.uid');

        $reserveResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.preview.stock_actual', 12)
            ->assertJsonPath('data.preview.stock_reservado_actual', 5)
            ->assertJsonPath('data.preview.stock_disponible', 7)
            ->assertJsonPath('data.item.reservation_indicator', 'partial')
            ->assertJsonPath('data.item.reserved_quantity', 3);

        $showResponse = $this->getJson("/api/quotations/{$quotationUid}");
        $showResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reservation_indicator', 'partial')
            ->assertJsonPath('data.items.0.reserved_quantity', 3)
            ->assertJsonPath('data.items.0.reservation_indicator', 'partial');

        $errorResponse = $this->postJson("/api/quotations/items/{$itemUid}/reserve-stock", [
            'quantity' => 3,
        ]);

        $errorResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.quantity.0', 'La reserva excede la cantidad pendiente del item de cotizacion');

        $releaseResponse = $this->deleteJson("/api/quotations/items/{$itemUid}/reservations/{$reservationUid}");
        $releaseResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item.reservation_indicator', 'not_reserved')
            ->assertJsonPath('data.item.reserved_quantity', 0);
    }

    public function test_quotation_routes_require_permissions(): void
    {
        $this->authenticate([]);

        $indexResponse = $this->getJson('/api/quotations');
        $storeResponse = $this->postJson('/api/quotations', [
            'quote_number' => 'COT-0002',
            'title' => 'Sin permiso',
        ]);

        $indexResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: quotations.read');

        $storeResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: quotations.create');
    }

    public function test_price_book_and_cpq_pricing_flow_calculates_discount_and_margin(): void
    {
        $user = $this->authenticate([
            'price-books.read',
            'price-books.manage',
            'quotations.read',
            'quotations.create',
            'quotations.update',
            'inventory.read',
            'inventory.manage',
            'inventory.reserve',
            'accounts.read',
        ]);

        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cliente Pricing',
            'document' => '901000002',
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Pricing Warehouse',
            'code' => 'PB-01',
        ]);

        $product = InventoryProduct::create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-PB-01',
            'name' => 'Licencia Anual',
            'cost_price' => 60,
            'reorder_point' => 0,
        ]);

        InventoryStock::create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'physical_stock' => 50,
            'reserved_stock' => 0,
        ]);

        $priceBookResponse = $this->postJson('/api/price-books', [
            'name' => 'Lista B2B',
            'key' => 'lista-b2b',
            'channel' => 'B2B',
            'items' => [
                [
                    'product_uid' => $product->uid,
                    'unit_price' => 100,
                    'min_margin_percent' => 20,
                ],
            ],
        ]);

        $priceBookUid = $priceBookResponse->json('data.uid');

        $priceBookResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'B2B')
            ->assertJsonPath('data.items.0.product_uid', $product->uid);

        $quotationResponse = $this->postJson('/api/quotations', [
            'quote_number' => 'COT-PB-001',
            'title' => 'Cotizacion con Price Book',
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'price_book_uid' => $priceBookUid,
        ]);

        $quotationUid = $quotationResponse->json('data.uid');

        $quotationResponse->assertCreated()
            ->assertJsonPath('data.price_book_uid', $priceBookUid);

        $itemResponse = $this->postJson("/api/quotations/{$quotationUid}/items", [
            'product_uid' => $product->uid,
            'warehouse_uid' => $warehouse->uid,
            'description' => 'Licencia corporativa',
            'quantity' => 2,
            'discount_percent' => 10,
        ]);

        $itemUid = $itemResponse->json('data.uid');

        $itemResponse->assertCreated()
            ->assertJsonPath('data.list_unit_price', '100.00')
            ->assertJsonPath('data.discount_percent', '10.00')
            ->assertJsonPath('data.discount_amount', '10.00')
            ->assertJsonPath('data.net_unit_price', '90.00')
            ->assertJsonPath('data.unit_cost', '60.00')
            ->assertJsonPath('data.margin_amount', '30.00')
            ->assertJsonPath('data.margin_percent', '33.33')
            ->assertJsonPath('data.min_margin_percent', '20.00')
            ->assertJsonPath('data.below_min_margin', false);

        $updateItemResponse = $this->putJson("/api/quotations/items/{$itemUid}", [
            'discount_percent' => 35,
        ]);

        $updateItemResponse->assertOk()
            ->assertJsonPath('data.net_unit_price', '65.00')
            ->assertJsonPath('data.margin_percent', '7.69')
            ->assertJsonPath('data.below_min_margin', true);

        $showResponse = $this->getJson("/api/quotations/{$quotationUid}");
        $showResponse->assertOk()
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.discount_total', 70)
            ->assertJsonPath('data.total', 130)
            ->assertJsonPath('data.items.0.below_min_margin', true);
    }

    public function test_price_book_routes_require_permissions(): void
    {
        $this->authenticate([]);

        $indexResponse = $this->getJson('/api/price-books');
        $storeResponse = $this->postJson('/api/price-books', [
            'name' => 'Sin permiso',
            'key' => 'sin-permiso',
        ]);

        $indexResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: price-books.read');

        $storeResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: price-books.manage');
    }

    public function test_commissions_are_generated_from_paid_financial_records(): void
    {
        $user = $this->authenticate([
            'commissions.read',
            'commissions.manage',
            'price-books.read',
            'price-books.manage',
            'quotations.read',
            'quotations.create',
            'quotations.update',
            'inventory.read',
            'inventory.manage',
            'accounts.read',
        ]);

        $account = Account::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Cliente Comisiones',
            'document' => '901000003',
            'owner_user_id' => $user->getKey(),
        ]);

        $product = InventoryProduct::create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-COM-01',
            'name' => 'Suite Empresarial',
            'cost_price' => 50,
        ]);

        $priceBook = PriceBook::create([
            'tenant_id' => $user->tenant_id,
            'name' => 'B2B Comisiones',
            'key' => 'b2b-comisiones',
            'channel' => 'B2B',
            'is_active' => true,
        ]);

        $priceBook->items()->create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $product->getKey(),
            'unit_price' => 100,
            'min_margin_percent' => 10,
        ]);

        $ruleResponse = $this->postJson('/api/commissions/rules', [
            'name' => 'Comision B2B Suite',
            'product_uid' => $product->uid,
            'customer_type' => 'B2B',
            'rate_percent' => 8,
        ]);

        $ruleResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_uid', $product->uid)
            ->assertJsonPath('data.customer_type', 'B2B')
            ->assertJsonPath('data.rate_percent', '8.00');

        $quotationResponse = $this->postJson('/api/quotations', [
            'quote_number' => 'COT-COM-001',
            'title' => 'Cotizacion Comisionable',
            'entity_type' => 'account',
            'entity_uid' => $account->uid,
            'price_book_uid' => $priceBook->uid,
        ]);

        $quotationUid = $quotationResponse->json('data.uid');

        $this->postJson("/api/quotations/{$quotationUid}/items", [
            'product_uid' => $product->uid,
            'description' => 'Suite Empresarial anual',
            'quantity' => 2,
            'discount_percent' => 0,
        ])->assertCreated();

        $recordResponse = $this->postJson('/api/commissions/financial-records', [
            'quotation_uid' => $quotationUid,
            'record_type' => 'invoice_paid',
            'external_reference' => 'FAC-EXT-001',
            'amount' => 200,
            'currency' => 'COP',
            'paid_at' => now()->toDateString(),
        ]);

        $recordResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.entries_count', 1)
            ->assertJsonPath('data.summary.commission_total', 16)
            ->assertJsonPath('data.commission_entries.0.rate_percent', '8.00')
            ->assertJsonPath('data.commission_entries.0.commission_amount', '16.00')
            ->assertJsonPath('data.commission_entries.0.status', 'earned');

        $summaryResponse = $this->getJson('/api/commissions/my-summary');
        $summaryResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.earned', 16)
            ->assertJsonPath('data.counts.earned', 1);

        $entryUid = $recordResponse->json('data.commission_entries.0.uid');

        $payResponse = $this->putJson("/api/commissions/entries/{$entryUid}/pay", [
            'paid_at' => now()->toDateString(),
        ]);

        $payResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_commission_routes_require_permissions(): void
    {
        $this->authenticate([]);

        $rulesResponse = $this->getJson('/api/commissions/rules');
        $recordResponse = $this->postJson('/api/commissions/financial-records', []);

        $rulesResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: commissions.read');

        $recordResponse->assertStatus(403)
            ->assertJsonPath('errors.permission.0', 'No tienes el permiso requerido: commissions.manage');
    }

    private function authenticate(?array $permissionKeys = null): User
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Demo',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
        ]);

        Sanctum::actingAs($user, [
            'access:full',
            'tenant:' . $tenant->uid,
        ]);

        $permissionKeys ??= [
            'accounts.read',
            'accounts.create',
            'accounts.update',
            'accounts.delete',
            'contacts.read',
            'contacts.create',
            'contacts.update',
            'contacts.delete',
            'relations.read',
            'relations.create',
            'relations.delete',
            'crm-entities.read',
            'crm-entities.create',
            'crm-entities.update',
            'tags.manage',
            'search.use',
            'dashboard.read',
            'tasks.read',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'interactions.read',
            'interactions.create',
            'activities.read',
            'activities.create',
            'activities.update',
            'activities.delete',
            'documents.read',
            'documents.create',
            'inventory.read',
            'inventory.manage',
            'inventory.reserve',
            'inventory.report',
            'quotations.read',
            'quotations.create',
            'quotations.update',
            'price-books.read',
            'price-books.manage',
            'commissions.read',
            'commissions.manage',
            'custom-fields.manage',
            'logs.read',
            'metrics.read',
            'plans.manage',
            'users.manage',
        ];

        $this->grantPermissions($user, $permissionKeys);

        return $user;
    }

    private function grantPermissions(User $user, array $permissionKeys): void
    {
        foreach ($permissionKeys as $permissionKey) {
            $permission = Permission::query()->firstOrCreate(
                ['key' => $permissionKey],
                [
                    'module' => explode('.', $permissionKey)[0],
                    'action' => explode('.', $permissionKey)[1] ?? 'manage',
                    'description' => $permissionKey,
                ]
            );

            $user->givePermissionTo($permission);
        }
    }

    private function actingAsApiUser(User $user, Tenant $tenant): void
    {
        Sanctum::actingAs($user, [
            'access:full',
            'tenant:' . $tenant->uid,
        ]);
    }
}
