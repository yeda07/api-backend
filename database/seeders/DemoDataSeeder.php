<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialRecord;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\Permission;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = $this->upsert(Tenant::class,
            ['name' => 'Demo Company'],
            [
                'is_active' => true,
                'expires_at' => now()->addYear(),
            ]
        );

        $admin = $this->upsert(User::class,
            ['email' => 'admin@demo.local'],
            [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Admin Demo',
                'password' => Hash::make('secret123'),
            ]
        );

        $manager = $this->upsert(User::class,
            ['email' => 'manager@demo.local'],
            [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Manager Demo',
                'password' => Hash::make('secret123'),
            ]
        );

        $seller = $this->upsert(User::class,
            ['email' => 'seller@demo.local'],
            [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Seller Demo',
                'password' => Hash::make('secret123'),
                'manager_id' => $manager->getKey(),
            ]
        );

        $allPermissions = Permission::query()->get();
        foreach ([$admin, $manager, $seller] as $user) {
            foreach ($allPermissions as $permission) {
                $user->givePermissionTo($permission);
            }
        }

        $account = $this->upsert(Account::class,
            ['tenant_id' => $tenant->getKey(), 'document' => '901999001'],
            [
                'name' => 'Acme Demo SAS',
                'email' => 'contacto@acme-demo.com',
                'industry' => 'Tecnologia',
                'phone' => '+57 3000001000',
                'address' => 'Bogota',
                'owner_user_id' => $seller->getKey(),
            ]
        );

        $contact = $this->upsert(Contact::class,
            ['tenant_id' => $tenant->getKey(), 'email' => 'compras@acme-demo.com'],
            [
                'account_id' => $account->getKey(),
                'first_name' => 'Laura',
                'last_name' => 'Compras',
                'phone' => '+57 3000001001',
                'position' => 'Jefe de Compras',
                'owner_user_id' => $seller->getKey(),
            ]
        );

        $stage = $this->upsert(OpportunityStage::class,
            ['tenant_id' => $tenant->getKey(), 'key' => 'proposal'],
            [
                'name' => 'Propuesta',
                'position' => 2,
                'probability_percent' => 60,
                'is_active' => true,
                'is_won' => false,
                'is_lost' => false,
            ]
        );

        $this->upsert(Opportunity::class,
            ['tenant_id' => $tenant->getKey(), 'title' => 'Renovacion infraestructura Acme'],
            [
                'owner_user_id' => $seller->getKey(),
                'stage_id' => $stage->getKey(),
                'opportunityable_type' => Account::class,
                'opportunityable_id' => $account->getKey(),
                'amount' => 15000000,
                'currency' => 'COP',
                'expected_close_date' => now()->addDays(20)->toDateString(),
                'description' => 'Oportunidad demo para renovacion de red',
            ]
        );

        $inventoryCategory = $this->upsert(InventoryCategory::class,
            ['tenant_id' => $tenant->getKey(), 'key' => 'networking'],
            [
                'name' => 'Networking',
                'description' => 'Equipos de red',
            ]
        );

        $warehouseMain = $this->upsert(Warehouse::class,
            ['tenant_id' => $tenant->getKey(), 'code' => 'MAIN'],
            [
                'name' => 'Bodega Principal',
                'location' => 'Bogota',
                'is_active' => true,
            ]
        );

        $warehouseStore = $this->upsert(Warehouse::class,
            ['tenant_id' => $tenant->getKey(), 'code' => 'STORE'],
            [
                'name' => 'Tienda',
                'location' => 'Bogota Norte',
                'is_active' => true,
            ]
        );

        $product = $this->upsert(InventoryProduct::class,
            ['tenant_id' => $tenant->getKey(), 'sku' => 'SKU-DEMO-001'],
            [
                'category_id' => $inventoryCategory->getKey(),
                'name' => 'Firewall Empresarial',
                'description' => 'Equipo principal de seguridad perimetral',
                'cost_price' => 2500,
                'reorder_point' => 2,
                'is_active' => true,
            ]
        );

        $this->upsert(InventoryStock::class,
            [
                'tenant_id' => $tenant->getKey(),
                'product_id' => $product->getKey(),
                'warehouse_id' => $warehouseMain->getKey(),
            ],
            [
                'physical_stock' => 12,
                'reserved_stock' => 2,
            ]
        );

        $this->upsert(InventoryStock::class,
            [
                'tenant_id' => $tenant->getKey(),
                'product_id' => $product->getKey(),
                'warehouse_id' => $warehouseStore->getKey(),
            ],
            [
                'physical_stock' => 4,
                'reserved_stock' => 0,
            ]
        );

        $priceBook = $this->upsert(PriceBook::class,
            ['tenant_id' => $tenant->getKey(), 'key' => 'b2b-demo'],
            [
                'name' => 'Lista B2B Demo',
                'channel' => 'B2B',
                'is_active' => true,
                'valid_from' => now()->startOfMonth()->toDateString(),
            ]
        );

        $this->upsert(PriceBookItem::class,
            [
                'tenant_id' => $tenant->getKey(),
                'price_book_id' => $priceBook->getKey(),
                'product_id' => $product->getKey(),
            ],
            [
                'unit_price' => 3900,
                'currency' => 'USD',
                'min_margin_percent' => 20,
            ]
        );

        $quotation = $this->upsert(Quotation::class,
            ['tenant_id' => $tenant->getKey(), 'quote_number' => 'COT-DEMO-001'],
            [
                'owner_user_id' => $seller->getKey(),
                'created_by_user_id' => $admin->getKey(),
                'price_book_id' => $priceBook->getKey(),
                'quoteable_type' => Account::class,
                'quoteable_id' => $account->getKey(),
                'title' => 'Cotizacion Demo Infraestructura',
                'status' => 'approved',
                'currency' => 'USD',
                'exchange_rate' => 4000,
                'local_currency' => 'COP',
                'valid_until' => now()->addDays(15)->toDateString(),
            ]
        );

        $this->upsert(QuotationItem::class,
            [
                'tenant_id' => $tenant->getKey(),
                'quotation_id' => $quotation->getKey(),
                'product_id' => $product->getKey(),
            ],
            [
                'warehouse_id' => $warehouseMain->getKey(),
                'sku' => $product->sku,
                'description' => $product->name,
                'quantity' => 2,
                'list_unit_price' => 3900,
                'discount_percent' => 5,
                'discount_amount' => 195,
                'net_unit_price' => 3705,
                'unit_cost' => 2500,
                'unit_price' => 3705,
                'margin_amount' => 1205,
                'margin_percent' => 32.52,
                'min_margin_percent' => 20,
                'below_min_margin' => false,
            ]
        );

        $this->upsert(FinancialRecord::class,
            ['tenant_id' => $tenant->getKey(), 'external_reference' => 'FAC-DEMO-001'],
            [
                'owner_user_id' => $seller->getKey(),
                'quotation_id' => $quotation->getKey(),
                'financeable_type' => Account::class,
                'financeable_id' => $account->getKey(),
                'record_type' => 'invoice_paid',
                'source_system' => 'demo_seed',
                'amount' => 29640000,
                'outstanding_amount' => 0,
                'currency' => 'COP',
                'issued_at' => now()->subDays(5)->toDateString(),
                'due_at' => now()->addDays(20)->toDateString(),
                'paid_at' => now()->subDay()->toDateString(),
                'status' => 'paid',
                'meta' => ['seeded' => true],
            ]
        );

        $expenseCategory = $this->upsert(ExpenseCategory::class,
            ['tenant_id' => $tenant->getKey(), 'key' => 'implementation'],
            [
                'name' => 'Implementacion',
                'description' => 'Gastos de despliegue',
                'is_active' => true,
            ]
        );

        $costCenter = $this->upsert(CostCenter::class,
            ['tenant_id' => $tenant->getKey(), 'key' => 'delivery'],
            [
                'name' => 'Delivery',
                'description' => 'Centro de costo de entrega',
                'is_active' => true,
            ]
        );

        $supplier = $this->upsert(Supplier::class,
            ['tenant_id' => $tenant->getKey(), 'name' => 'Proveedor Demo'],
            [
                'contact_name' => 'Carlos Proveedor',
                'email' => 'proveedor@demo.local',
                'phone' => '+57 3000002000',
                'payment_terms_days' => 15,
                'is_active' => true,
            ]
        );

        $this->upsert(Expense::class,
            ['tenant_id' => $tenant->getKey(), 'title' => 'Desplazamiento instalacion demo'],
            [
                'expense_category_id' => $expenseCategory->getKey(),
                'supplier_id' => $supplier->getKey(),
                'owner_user_id' => $seller->getKey(),
                'expenseable_type' => Account::class,
                'expenseable_id' => $account->getKey(),
                'cost_center_id' => $costCenter->getKey(),
                'cost_center' => 'delivery',
                'amount' => 350000,
                'currency' => 'COP',
                'expense_date' => now()->subDays(2)->toDateString(),
                'status' => 'approved',
                'description' => 'Viaticos e instalacion',
            ]
        );

        $purchaseOrder = $this->upsert(PurchaseOrder::class,
            ['tenant_id' => $tenant->getKey(), 'purchase_number' => 'OC-DEMO-001'],
            [
                'supplier_id' => $supplier->getKey(),
                'owner_user_id' => $manager->getKey(),
                'source_type' => Account::class,
                'source_uid' => $account->uid,
                'cost_center_id' => $costCenter->getKey(),
                'cost_center' => 'delivery',
                'status' => 'approved',
                'currency' => 'COP',
                'paid_total' => 2000000,
                'ordered_at' => now()->subDays(7)->toDateString(),
                'expected_at' => now()->subDays(3)->toDateString(),
                'due_date' => now()->addDays(8)->toDateString(),
                'received_at' => now()->subDays(3)->toDateString(),
            ]
        );

        $this->upsert(PurchaseOrderItem::class,
            [
                'tenant_id' => $tenant->getKey(),
                'purchase_order_id' => $purchaseOrder->getKey(),
                'product_id' => $product->getKey(),
            ],
            [
                'warehouse_id' => $warehouseMain->getKey(),
                'description' => 'Reposicion firewall demo',
                'quantity' => 3,
                'unit_cost' => 2500,
                'received_quantity' => 3,
            ]
        );

        $this->upsert(Task::class,
            ['tenant_id' => $tenant->getKey(), 'title' => 'Llamar cliente demo'],
            [
                'owner_user_id' => $seller->getKey(),
                'assigned_user_id' => $seller->getKey(),
                'description' => 'Confirmar fecha de entrega',
                'status' => 'pending',
                'priority' => 'high',
                'due_date' => now()->addDays(1)->toDateString(),
                'taskable_type' => Account::class,
                'taskable_id' => $account->getKey(),
            ]
        );

        $this->upsert(Activity::class,
            ['tenant_id' => $tenant->getKey(), 'title' => 'Reunion comercial demo'],
            [
                'owner_user_id' => $seller->getKey(),
                'assigned_user_id' => $seller->getKey(),
                'type' => 'meeting',
                'description' => 'Revisar alcance final del proyecto comercial',
                'status' => 'pending',
                'scheduled_at' => now()->addDays(2),
                'activityable_type' => Account::class,
                'activityable_id' => $account->getKey(),
            ]
        );

        $this->upsert(Tag::class,
            ['tenant_id' => $tenant->getKey(), 'key' => 'vip'],
            [
                'name' => 'VIP',
                'color' => '#16a34a',
            ]
        );
    }

    private function upsert(string $modelClass, array $lookup, array $values)
    {
        $model = $modelClass::query()->firstOrNew($lookup);
        $model->fill($values);

        if (empty($model->uid) && in_array('uid', $model->getFillable(), true)) {
            $model->uid = (string) Str::uuid();
        }

        $model->save();

        return $model;
    }
}
