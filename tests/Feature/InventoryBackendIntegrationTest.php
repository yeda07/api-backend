<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_master_and_warehouses_include_frontend_summaries(): void
    {
        $user = $this->authenticateWithPermissions(['inventory.read']);

        $activeWarehouse = Warehouse::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Bodega Central',
            'code' => 'BCN01',
            'is_active' => true,
        ]);
        Warehouse::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Bodega Inactiva',
            'code' => 'BIN01',
            'is_active' => false,
        ]);

        $normalProduct = InventoryProduct::query()->create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-001',
            'name' => 'Camiseta Basica XL',
            'cost_price' => 1200,
            'reorder_point' => 10,
            'is_active' => true,
        ]);
        $outProduct = InventoryProduct::query()->create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-002',
            'name' => 'Gorra',
            'cost_price' => 500,
            'reorder_point' => 5,
            'is_active' => false,
        ]);

        InventoryStock::query()->create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $normalProduct->getKey(),
            'warehouse_id' => $activeWarehouse->getKey(),
            'physical_stock' => 50,
            'reserved_stock' => 5,
        ]);
        InventoryStock::query()->create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $outProduct->getKey(),
            'warehouse_id' => $activeWarehouse->getKey(),
            'physical_stock' => 0,
            'reserved_stock' => 0,
        ]);

        $this->getJson('/api/inventory/master')
            ->assertOk()
            ->assertJsonPath('data.summary.products', 2)
            ->assertJsonPath('data.summary.active_products', 1)
            ->assertJsonPath('data.summary.out_of_stock_count', 1)
            ->assertJsonPath('data.summary.total_physical_stock', 50)
            ->assertJsonPath('data.summary.total_reserved_stock', 5)
            ->assertJsonPath('data.summary.total_available_stock', 45)
            ->assertJsonPath('data.data.0.unit_cost', 1200)
            ->assertJsonPath('data.data.0.stocks.0.available_stock', 45);

        $this->getJson('/api/inventory/warehouses')
            ->assertOk()
            ->assertJsonPath('summary.total_warehouses', 2)
            ->assertJsonPath('summary.active_warehouses', 1)
            ->assertJsonPath('data.0.summary.sku_count', 1)
            ->assertJsonPath('data.0.summary.total_physical', 50)
            ->assertJsonPath('data.0.summary.total_reserved', 5)
            ->assertJsonPath('data.0.summary.total_available', 45)
            ->assertJsonPath('data.0.summary.total_value', 60000);
    }

    public function test_inventory_filters_search_and_active_status_for_frontend(): void
    {
        $user = $this->authenticateWithPermissions(['inventory.read']);

        $central = Warehouse::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Bodega Central',
            'code' => 'BCN01',
            'is_active' => true,
        ]);
        Warehouse::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Bodega Norte',
            'code' => 'NRT02',
            'is_active' => true,
        ]);

        $shirt = InventoryProduct::query()->create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-CAM-001',
            'name' => 'Camiseta Basica XL',
            'is_active' => true,
        ]);
        $cap = InventoryProduct::query()->create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-GOR-002',
            'name' => 'Gorra Promocional',
            'is_active' => false,
        ]);

        InventoryMovement::query()->create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $cap->getKey(),
            'to_warehouse_id' => $central->getKey(),
            'performed_by_user_id' => $user->getKey(),
            'type' => 'adjustment_in',
            'quantity' => 10,
            'reference_uid' => 'REF-GORRA-10',
        ]);
        InventoryMovement::query()->create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $shirt->getKey(),
            'to_warehouse_id' => $central->getKey(),
            'performed_by_user_id' => $user->getKey(),
            'type' => 'adjustment_in',
            'quantity' => 5,
            'reference_uid' => 'REF-CAMISETA-5',
        ]);

        $this->getJson('/api/inventory/master?search=gorra')
            ->assertOk()
            ->assertJsonPath('data.summary.products', 1)
            ->assertJsonPath('data.data.0.sku', 'SKU-GOR-002');

        $this->getJson('/api/inventory/master?is_active=false')
            ->assertOk()
            ->assertJsonPath('data.summary.products', 1)
            ->assertJsonPath('data.data.0.name', 'Gorra Promocional');

        $this->getJson('/api/inventory/warehouses?search=NRT')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'NRT02');

        $this->getJson('/api/inventory/movements?search=gorra')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference_uid', 'REF-GORRA-10');
    }

    public function test_bulk_adjust_and_movements_summary_match_inventory_document(): void
    {
        $user = $this->authenticateWithPermissions(['inventory.read', 'inventory.manage']);

        $warehouse = Warehouse::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Bodega Central',
            'code' => 'BCN01',
            'is_active' => true,
        ]);
        $firstProduct = InventoryProduct::query()->create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-001',
            'name' => 'Camiseta Basica XL',
        ]);
        $secondProduct = InventoryProduct::query()->create([
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-002',
            'name' => 'Gorra',
        ]);

        $this->postJson('/api/inventory/stocks/adjust/bulk', [
            'warehouse_uid' => $warehouse->uid,
            'comment' => 'Entrada OC-0089',
            'items' => [
                ['product_uid' => $firstProduct->uid, 'quantity' => 50],
                ['product_uid' => $secondProduct->uid, 'quantity' => 20],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.summary.items', 2)
            ->assertJsonPath('data.summary.total_quantity', 70);

        $this->postJson('/api/inventory/stocks/adjust', [
            'product_uid' => $firstProduct->uid,
            'warehouse_uid' => $warehouse->uid,
            'operation' => 'out',
            'quantity' => 5,
        ])->assertOk();

        $oldMovement = InventoryMovement::query()->create([
            'tenant_id' => $user->tenant_id,
            'product_id' => $firstProduct->getKey(),
            'from_warehouse_id' => $warehouse->getKey(),
            'to_warehouse_id' => $warehouse->getKey(),
            'performed_by_user_id' => $user->getKey(),
            'type' => 'transfer',
            'quantity' => 1,
        ]);
        $oldMovement->forceFill([
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ])->save();

        $this->getJson('/api/inventory/movements/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.entries', 2)
            ->assertJsonPath('data.transfers', 0)
            ->assertJsonPath('data.adjustments', 1);
    }

    public function test_products_accept_quantity_and_unit_cost_names_from_frontend_contract(): void
    {
        $user = $this->authenticateWithPermissions(['inventory.read', 'inventory.manage']);
        $warehouse = Warehouse::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Bodega Central',
            'code' => 'BCN01',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/inventory/products', [
            'sku' => 'SKU-003',
            'name' => 'Zapatos',
            'unit_cost' => 900,
            'warehouse_stocks' => [
                ['warehouse_uid' => $warehouse->uid, 'quantity' => 7],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.unit_cost', 900);

        $this->assertDatabaseHas('inventory_products', [
            'tenant_id' => $user->tenant_id,
            'sku' => 'SKU-003',
            'cost_price' => 900,
        ]);
        $this->assertDatabaseHas('inventory_stocks', [
            'tenant_id' => $user->tenant_id,
            'warehouse_id' => $warehouse->getKey(),
            'physical_stock' => 7,
        ]);
    }

    private function authenticateWithPermissions(array $permissionKeys): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Test',
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach ($permissionKeys as $key) {
            Permission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'module' => 'inventory',
                    'action' => 'manage',
                    'description' => $key,
                ]
            );
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Tester',
            'email' => 'tester+' . uniqid() . '@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $user->permissions()->sync($permissionIds);

        Sanctum::actingAs($user, ['access:full', 'tenant:' . $tenant->uid]);

        return $user;
    }
}
