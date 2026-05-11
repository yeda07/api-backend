<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CrmEntity;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_dashboard_matches_sales_frontend_contract(): void
    {
        $user = $this->authenticateWithPermissions(['finance.read']);
        $account = $this->account($user);

        Invoice::query()->create([
            'tenant_id' => $user->tenant_id,
            'invoiceable_type' => Account::class,
            'invoiceable_id' => $account->getKey(),
            'invoice_number' => 'FAC-CURRENT-' . uniqid(),
            'status' => 'partial',
            'currency' => 'USD',
            'total' => 1000,
            'outstanding_total' => 400,
            'issued_at' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        Invoice::query()->create([
            'tenant_id' => $user->tenant_id,
            'invoiceable_type' => Account::class,
            'invoiceable_id' => $account->getKey(),
            'invoice_number' => 'FAC-OVERDUE-' . uniqid(),
            'status' => 'overdue',
            'currency' => 'USD',
            'total' => 200,
            'outstanding_total' => 200,
            'issued_at' => now()->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        Invoice::query()->create([
            'tenant_id' => $user->tenant_id,
            'invoiceable_type' => Account::class,
            'invoiceable_id' => $account->getKey(),
            'invoice_number' => 'FAC-PREV-' . uniqid(),
            'status' => 'paid',
            'currency' => 'USD',
            'total' => 600,
            'outstanding_total' => 0,
            'issued_at' => now()->subMonthNoOverflow()->toDateString(),
            'due_date' => now()->subMonthNoOverflow()->addDays(10)->toDateString(),
        ]);

        $this->getJson('/api/finance/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.monthly_sales', 1200)
            ->assertJsonPath('data.stats.monthly_sales_growth_percent', 100)
            ->assertJsonPath('data.stats.pending_invoices_count', 1)
            ->assertJsonPath('data.stats.pending_invoices_amount', 400)
            ->assertJsonPath('data.stats.overdue_portfolio', 200)
            ->assertJsonPath('data.stats.overdue_clients_count', 1)
            ->assertJsonPath('data.stats.margin_target_percent', 45)
            ->assertJsonPath('data.recent_invoices.0.client_name', 'Cliente Ventas')
            ->assertJsonStructure(['data' => ['weekly_sales', 'recent_invoices']]);
    }

    public function test_credit_rules_and_exceptions_match_sales_contract(): void
    {
        $user = $this->authenticateWithPermissions(['finance.read', 'finance.manage']);
        $account = $this->account($user);

        $this->getJson('/api/finance/credit/rules')
            ->assertOk()
            ->assertJsonPath('data.max_days', 30)
            ->assertJsonPath('data.max_amount', 50000)
            ->assertJsonPath('data.auto_block', true);

        $this->putJson('/api/finance/credit/rules', [
            'max_days' => 45,
            'max_amount' => 125000,
            'auto_block' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.max_days', 45)
            ->assertJsonPath('data.max_amount', 125000)
            ->assertJsonPath('data.auto_block', false);

        $created = $this->postJson('/api/finance/credit/exceptions', [
            'client_uid' => $account->uid,
            'client_identifier' => 'RFC-123',
            'credit_limit' => 150000,
            'max_days' => 60,
            'is_active' => true,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.client_name', 'Cliente Ventas')
            ->assertJsonPath('data.client_identifier', 'RFC-123')
            ->assertJsonPath('data.credit_limit', 150000)
            ->assertJsonPath('data.max_days', 60)
            ->assertJsonPath('data.is_active', true);

        $uid = $created->json('data.uid');

        $this->putJson('/api/finance/credit/exceptions/' . $uid, [
            'credit_limit' => 175000,
            'max_days' => 75,
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.credit_limit', 175000)
            ->assertJsonPath('data.max_days', 75)
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/finance/credit/exceptions')
            ->assertOk()
            ->assertJsonPath('data.0.uid', $uid);
    }

    public function test_currency_rates_and_conversion_match_sales_contract(): void
    {
        $user = $this->authenticateWithPermissions(['finance.read']);
        Currency::query()->create([
            'code' => 'COP',
            'name' => 'Peso colombiano',
            'symbol' => '$',
        ]);

        ExchangeRate::query()->create([
            'tenant_id' => $user->tenant_id,
            'from_currency' => 'USD',
            'to_currency' => 'COP',
            'rate' => 4000,
            'rate_date' => now()->toDateString(),
        ]);

        $this->getJson('/api/currency/rates')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'COP')
            ->assertJsonPath('data.0.name', 'Peso colombiano')
            ->assertJsonPath('data.0.rate', 4000)
            ->assertJsonPath('data.0.status', 'active');

        $this->postJson('/api/currency/convert', [
            'from' => 'USD',
            'to' => 'COP',
            'amount' => 1000,
        ])
            ->assertOk()
            ->assertJsonPath('data.result', 4000000)
            ->assertJsonPath('data.rate', 4000)
            ->assertJsonPath('data.rate_date', now()->toDateString());
    }

    public function test_quotations_and_invoices_support_server_search_filters(): void
    {
        $user = $this->authenticateWithPermissions(['quotations.read', 'finance.read']);
        $account = $this->account($user);

        $matchingQuote = Quotation::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'quoteable_type' => Account::class,
            'quoteable_id' => $account->getKey(),
            'quote_number' => 'Q-SALES-001',
            'title' => 'Renovacion Enterprise',
            'status' => 'approved',
        ]);
        Quotation::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'quote_number' => 'Q-SALES-002',
            'title' => 'Borrador interno',
            'status' => 'draft',
        ]);

        Invoice::query()->create([
            'tenant_id' => $user->tenant_id,
            'quotation_id' => $matchingQuote->getKey(),
            'invoiceable_type' => Account::class,
            'invoiceable_id' => $account->getKey(),
            'invoice_number' => 'INV-ENTERPRISE-001',
            'status' => 'issued',
            'currency' => 'USD',
            'total' => 1000,
            'outstanding_total' => 1000,
        ]);
        Invoice::query()->create([
            'tenant_id' => $user->tenant_id,
            'invoiceable_type' => Account::class,
            'invoiceable_id' => $account->getKey(),
            'invoice_number' => 'INV-OTHER-001',
            'status' => 'paid',
            'currency' => 'USD',
            'total' => 500,
            'outstanding_total' => 0,
        ]);

        $this->getJson('/api/quotations?search=enterprise&status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uid', $matchingQuote->uid);

        $this->getJson('/api/quotations?search='.$account->uid)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uid', $matchingQuote->uid);

        $this->getJson('/api/finance/invoices?search='.$matchingQuote->uid)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'INV-ENTERPRISE-001');
    }

    public function test_quotation_create_auto_generates_quote_number(): void
    {
        $this->authenticateWithPermissions(['quotations.create']);
        $year = now()->format('Y');

        $first = $this->postJson('/api/quotations', [
            'title' => 'Cotizacion sin numero',
            'status' => 'draft',
        ]);

        $first->assertCreated()
            ->assertJsonPath('data.quote_number', 'COT-' . $year . '-001');

        $second = $this->postJson('/api/quotations', [
            'title' => 'Cotizacion sin numero 2',
            'status' => 'draft',
        ]);

        $second->assertCreated()
            ->assertJsonPath('data.quote_number', 'COT-' . $year . '-002');
    }

    public function test_quotation_show_accepts_opportunity_uid_when_quote_belongs_to_opportunity(): void
    {
        $user = $this->authenticateWithPermissions(['quotations.read']);
        $stage = OpportunityStage::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Nuevo',
            'key' => 'new',
            'position' => 1,
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'stage_id' => $stage->getKey(),
            'title' => 'Oportunidad con cotizacion',
            'amount' => 12000,
        ]);
        $quotation = Quotation::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'quoteable_type' => Opportunity::class,
            'quoteable_id' => $opportunity->getKey(),
            'quote_number' => 'Q-OPP-' . uniqid(),
            'title' => 'Cotizacion desde oportunidad',
            'status' => 'draft',
        ]);

        $this->getJson('/api/quotations/' . $opportunity->uid)
            ->assertOk()
            ->assertJsonPath('data.uid', $quotation->uid)
            ->assertJsonPath('data.opportunity_uid', $opportunity->uid);
    }

    public function test_opportunity_board_supports_origin_and_product_filters(): void
    {
        $user = $this->authenticateWithPermissions(['opportunities.read']);
        $stage = OpportunityStage::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Nuevo',
            'key' => 'new',
            'position' => 1,
        ]);
        $entity = CrmEntity::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'type' => 'B2B',
            'profile_data' => [
                'company_name' => 'Cuenta Pipeline',
                'lead_origin' => 'web',
                'product_uid' => 'prod-crm',
            ],
        ]);

        Opportunity::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'stage_id' => $stage->getKey(),
            'opportunityable_type' => CrmEntity::class,
            'opportunityable_id' => $entity->getKey(),
            'title' => 'CRM Enterprise',
            'amount' => 25000,
            'description' => 'Producto principal CRM',
        ]);
        Opportunity::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'stage_id' => $stage->getKey(),
            'title' => 'Servicio soporte',
            'amount' => 1000,
            'description' => 'Origen referido',
        ]);

        $this->getJson('/api/opportunities/board?origin=web&product=prod-crm')
            ->assertOk()
            ->assertJsonPath('data.stages.0.summary.count', 1)
            ->assertJsonPath('data.stages.0.items.0.title', 'CRM Enterprise');
    }

    private function account(User $user): Account
    {
        return Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'owner_user_id' => $user->getKey(),
            'name' => 'Cliente Ventas',
            'document' => 'SALES-' . uniqid(),
        ]);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Sales',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => 'finance',
                    'action' => $key,
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Sales Owner',
            'email' => 'sales-owner+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
