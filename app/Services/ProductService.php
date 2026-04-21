<?php

namespace App\Services;

use App\Models\InventoryProduct;
use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductVersionService $productVersionService,
        private readonly DependencyService $dependencyService
    ) {
    }

    public function index(array $filters = [])
    {
        return $this->products->all($filters);
    }

    public function show(string $uid): Product
    {
        return $this->products->findByUid($uid);
    }

    public function create(array $data): Product
    {
        $validated = $this->validateProduct($data);
        $this->ensureUniqueSku($validated['sku']);

        $product = $this->products->create([
            'inventory_product_id' => !empty($validated['inventory_product_uid']) ? $this->resolveInventoryProduct($validated['inventory_product_uid'])->getKey() : null,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        if (!empty($validated['initial_version'])) {
            $this->productVersionService->createVersion($product, [
                'version' => $validated['initial_version'],
                'release_date' => $validated['initial_release_date'] ?? null,
                'status' => 'active',
            ]);
        }

        return $product->fresh(['inventoryProduct', 'versions']);
    }

    public function update(string $uid, array $data): Product
    {
        $product = $this->show($uid);
        $validated = $this->validateProduct($data, true);

        if (isset($validated['sku']) && $validated['sku'] !== $product->sku) {
            $this->ensureUniqueSku($validated['sku'], $product->getKey());
        }

        $payload = $validated;

        if (array_key_exists('inventory_product_uid', $validated)) {
            $payload['inventory_product_id'] = $validated['inventory_product_uid']
                ? $this->resolveInventoryProduct($validated['inventory_product_uid'])->getKey()
                : null;
            unset($payload['inventory_product_uid']);
        }

        unset($payload['initial_version'], $payload['initial_release_date']);

        return $this->products->update($product, $payload);
    }

    public function delete(string $uid): void
    {
        $product = $this->show($uid);
        $this->products->delete($product);
    }

    public function createVersion(string $productUid, array $data)
    {
        $product = $this->show($productUid);

        return $this->productVersionService->createVersion($product, $data);
    }

    public function versions(string $productUid)
    {
        return $this->show($productUid)->versions()->latest('release_date')->get();
    }

    public function dependencies(string $productUid)
    {
        return $this->dependencyService->listForProduct($productUid);
    }

    public function createDependency(string $productUid, array $data)
    {
        $product = $this->show($productUid);

        $validated = Validator::make($data, [
            'depends_on_product_uid' => 'required|uuid',
            'dependency_type' => 'required|string|in:required,optional,incompatible',
            'message' => 'nullable|string',
        ])->validate();

        $dependsOnProduct = $this->show($validated['depends_on_product_uid']);

        return $this->dependencyService->createDependency($product, $dependsOnProduct, $validated);
    }

    public function findByUid(string $uid): Product
    {
        return $this->show($uid);
    }

    private function validateProduct(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$partial ? 'sometimes' : 'required', 'string', 'in:product,service'],
            'sku' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:active,inactive',
            'inventory_product_uid' => 'nullable|uuid',
            'initial_version' => 'nullable|string|max:50',
            'initial_release_date' => 'nullable|date',
        ])->validate();
    }

    private function ensureUniqueSku(string $sku, ?int $ignoreId = null): void
    {
        $exists = Product::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('sku', $sku)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sku' => ['Ya existe un producto o servicio con este SKU en el tenant'],
            ]);
        }
    }

    private function resolveInventoryProduct(string $uid): InventoryProduct
    {
        return InventoryProduct::query()->where('uid', $uid)->firstOr(function () {
            throw ValidationException::withMessages([
                'inventory_product_uid' => ['El producto de inventario asociado no existe o no pertenece a este tenant'],
            ]);
        });
    }
}
