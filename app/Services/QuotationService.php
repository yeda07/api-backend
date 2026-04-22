<?php

namespace App\Services;

use App\Support\ApiIndex;
use App\Models\InventoryProduct;
use App\Models\InventoryReservation;
use App\Models\Account;
use App\Models\PriceBook;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly PriceBookService $priceBookService,
        private readonly PricingService $pricingService,
        private readonly CreditService $creditService,
        private readonly DocumentValidationService $documentValidationService,
        private readonly ProductService $productService,
        private readonly DependencyService $dependencyService
    )
    {
    }

    public function getAll(array $filters = [])
    {
        return ApiIndex::paginateOrGet(
            Quotation::query()->with(['priceBook', 'items.product', 'items.catalogProduct', 'items.warehouse', 'quoteable'])->latest(),
            $filters,
            'quotations_page'
        );
    }

    public function getByUid(string $uid): Quotation
    {
        return Quotation::query()->with(['priceBook', 'items.product', 'items.catalogProduct', 'items.warehouse', 'quoteable'])->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): Quotation
    {
        $validated = $this->validateQuotation($data);

        return DB::transaction(function () use ($validated) {
            $quoteable = $this->resolveQuoteable($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $priceBook = $this->resolvePriceBook($validated['price_book_uid'] ?? null);
            if ($quoteable) {
                $this->creditService->ensureCanOperate($quoteable);
                $this->ensureDocumentsReady($quoteable);
            }

            $quotation = Quotation::query()->create([
                'owner_user_id' => $quoteable?->owner_user_id ?? auth()->id(),
                'created_by_user_id' => auth()->id(),
                'price_book_id' => $priceBook?->getKey(),
                'quoteable_type' => $quoteable ? get_class($quoteable) : null,
                'quoteable_id' => $quoteable?->getKey(),
                'quote_number' => $validated['quote_number'],
                'title' => $validated['title'],
                'status' => $validated['status'] ?? 'draft',
                'currency' => $validated['currency'] ?? null,
                'exchange_rate' => $validated['exchange_rate'] ?? 1,
                'local_currency' => $validated['local_currency'] ?? (auth()->user()?->tenant?->currency?->code ?? 'COP'),
                'valid_until' => $validated['valid_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return $quotation->fresh(['priceBook', 'items.product', 'items.catalogProduct', 'items.warehouse', 'quoteable']);
        });
    }

    public function update(string $uid, array $data): Quotation
    {
        $quotation = $this->getByUid($uid);
        $validated = $this->validateQuotation($data, true);

        return DB::transaction(function () use ($quotation, $validated) {
            $payload = [];
            $previousStatus = $quotation->status;

            foreach (['quote_number', 'title', 'status', 'currency', 'exchange_rate', 'local_currency', 'valid_until', 'notes'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            if (array_key_exists('price_book_uid', $validated)) {
                $payload['price_book_id'] = $this->resolvePriceBook($validated['price_book_uid'])?->getKey();
            }

            if (array_key_exists('entity_type', $validated) || array_key_exists('entity_uid', $validated)) {
                $quoteable = $this->resolveQuoteable($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
                $payload['quoteable_type'] = $quoteable ? get_class($quoteable) : null;
                $payload['quoteable_id'] = $quoteable?->getKey();
                $payload['owner_user_id'] = $quoteable?->owner_user_id ?? $quotation->owner_user_id;
            }

            if ($payload !== []) {
                $quotation->update($payload);
            }

            $quotation = $quotation->fresh(['priceBook', 'items.product', 'items.catalogProduct', 'items.warehouse', 'quoteable']);
            if ($quotation->quoteable) {
                $this->creditService->ensureCanOperate($quotation->quoteable);
                $this->ensureDocumentsReady($quotation->quoteable);
            }

            if (($payload['status'] ?? null) === 'approved' && $previousStatus !== 'approved') {
                $this->validateQuotationDependencies($quotation);
                $this->reservePendingQuotationStock($quotation);
                $quotation = $quotation->fresh(['priceBook', 'items.product', 'items.catalogProduct', 'items.warehouse', 'quoteable']);
            }

            return $quotation;
        });
    }

    private function ensureDocumentsReady(object $entity): void
    {
        if ($entity instanceof Account) {
            $this->documentValidationService->ensureReadyForAccount($entity);
        }
    }

    public function addItem(string $quotationUid, array $data): QuotationItem
    {
        $quotation = $this->getByUid($quotationUid);
        $validated = $this->validateItem($data);

        return DB::transaction(function () use ($quotation, $validated) {
            $catalogProduct = $this->resolveCatalogProduct($validated['catalog_product_uid'] ?? null);
            $product = $this->resolveProduct(
                $validated['product_uid'] ?? $catalogProduct?->inventoryProduct?->uid
            );
            $warehouse = $this->resolveWarehouse($validated['warehouse_uid'] ?? null);
            $pricing = $this->buildPricingPayload(
                $quotation->priceBook,
                $product,
                (int) $validated['quantity'],
                $validated['unit_price'] ?? null,
                $validated['discount_percent'] ?? 0,
                $validated['discount_amount'] ?? null
            );

            $item = QuotationItem::query()->create([
                'quotation_id' => $quotation->getKey(),
                'product_id' => $product?->getKey(),
                'catalog_product_id' => $catalogProduct?->getKey(),
                'warehouse_id' => $warehouse?->getKey(),
                'sku' => $validated['sku'] ?? $catalogProduct?->sku ?? $product?->sku,
                'description' => $validated['description'] ?? $catalogProduct?->name,
                'quantity' => $validated['quantity'],
                'list_unit_price' => $pricing['list_unit_price'],
                'discount_percent' => $pricing['discount_percent'],
                'discount_amount' => $pricing['discount_amount'],
                'net_unit_price' => $pricing['net_unit_price'],
                'unit_price' => $pricing['net_unit_price'],
                'unit_cost' => $pricing['unit_cost'],
                'margin_amount' => $pricing['margin_amount'],
                'margin_percent' => $pricing['margin_percent'],
                'min_margin_percent' => $pricing['min_margin_percent'],
                'below_min_margin' => $pricing['below_min_margin'],
            ]);

            if ($catalogProduct) {
                $this->syncRequiredDependencies($quotation, $catalogProduct, $item);
            }

            return $item->fresh(['quotation', 'product', 'catalogProduct', 'warehouse']);
        });
    }

    public function updateItem(string $itemUid, array $data): QuotationItem
    {
        $item = $this->getItemByUid($itemUid);
        $validated = $this->validateItem($data, true);

        return DB::transaction(function () use ($item, $validated) {
            $payload = [];
            $product = $item->product;
            $catalogProduct = $item->catalogProduct;

            if (array_key_exists('product_uid', $validated)) {
                $product = $this->resolveProduct($validated['product_uid']);
                $payload['product_id'] = $product?->getKey();
            }

            if (array_key_exists('catalog_product_uid', $validated)) {
                $catalogProduct = $this->resolveCatalogProduct($validated['catalog_product_uid']);
                $payload['catalog_product_id'] = $catalogProduct?->getKey();

                if (!array_key_exists('product_uid', $validated)) {
                    $product = $this->resolveProduct($catalogProduct?->inventoryProduct?->uid);
                    $payload['product_id'] = $product?->getKey();
                }
            }

            if (array_key_exists('warehouse_uid', $validated)) {
                $payload['warehouse_id'] = $this->resolveWarehouse($validated['warehouse_uid'])?->getKey();
            }

            foreach (['sku', 'description', 'quantity'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            if (array_key_exists('quantity', $validated) && $item->reserved_quantity > (int) $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => ['La cantidad no puede ser menor que el stock ya reservado'],
                ]);
            }

            if (
                array_key_exists('quantity', $validated)
                || array_key_exists('product_uid', $validated)
                || array_key_exists('unit_price', $validated)
                || array_key_exists('discount_percent', $validated)
                || array_key_exists('discount_amount', $validated)
            ) {
                $quotation = $item->quotation ?? $this->getByUid($item->quotation_uid);
                $quantity = (int) ($validated['quantity'] ?? $item->quantity);
                $pricing = $this->buildPricingPayload(
                    $quotation->priceBook,
                    $product,
                    $quantity,
                    $validated['unit_price'] ?? null,
                    $validated['discount_percent'] ?? (float) $item->discount_percent,
                    $validated['discount_amount'] ?? null
                );

                $payload = array_merge($payload, [
                    'list_unit_price' => $pricing['list_unit_price'],
                    'discount_percent' => $pricing['discount_percent'],
                    'discount_amount' => $pricing['discount_amount'],
                    'net_unit_price' => $pricing['net_unit_price'],
                    'unit_price' => $pricing['net_unit_price'],
                    'unit_cost' => $pricing['unit_cost'],
                    'margin_amount' => $pricing['margin_amount'],
                    'margin_percent' => $pricing['margin_percent'],
                    'min_margin_percent' => $pricing['min_margin_percent'],
                    'below_min_margin' => $pricing['below_min_margin'],
                ]);
            }

            $item->update($payload);

            if ($catalogProduct) {
                $this->syncRequiredDependencies($item->quotation, $catalogProduct, $item);
            }

            return $item->fresh(['quotation', 'product', 'catalogProduct', 'warehouse']);
        });
    }

    public function deleteItem(string $itemUid): void
    {
        $item = $this->getItemByUid($itemUid);

        if ($item->reserved_quantity > 0) {
            throw ValidationException::withMessages([
                'item' => ['No puedes eliminar un item con stock reservado activo'],
            ]);
        }

        $item->delete();
    }

    public function reserveItemStock(string $itemUid, array $data): array
    {
        $item = $this->getItemByUid($itemUid);
        $validated = Validator::make($data, [
            'quantity' => 'required|integer|min:1',
            'comment' => 'nullable|string',
        ])->validate();

        if (!$item->product_id || !$item->warehouse_id) {
            throw ValidationException::withMessages([
                'item' => ['El item debe tener producto y bodega para reservar stock'],
            ]);
        }

        $remaining = $item->quantity - $item->reserved_quantity;

        if ((int) $validated['quantity'] > $remaining) {
            throw ValidationException::withMessages([
                'quantity' => ['La reserva excede la cantidad pendiente del item de cotizacion'],
            ]);
        }

        $result = $this->inventoryService->reserveStock([
            'product_uid' => $item->product_uid,
            'warehouse_uid' => $item->warehouse_uid,
            'quantity' => (int) $validated['quantity'],
            'source_type' => 'quotation_item',
            'source_uid' => $item->uid,
            'comment' => $validated['comment'] ?? null,
        ]);

        return [
            'quotation_uid' => $item->quotation_uid,
            'item' => $item->fresh(['quotation', 'product', 'warehouse']),
            'reservation' => $result['reservation'],
            'preview' => $result['preview'],
        ];
    }

    public function releaseItemReservation(string $itemUid, string $reservationUid): array
    {
        $item = $this->getItemByUid($itemUid);
        $reservation = InventoryReservation::query()
            ->where('uid', $reservationUid)
            ->where('source_type', 'quotation_item')
            ->where('source_uid', $item->uid)
            ->firstOrFail();

        $result = $this->inventoryService->releaseReservation($reservation->uid);

        return [
            'quotation_uid' => $item->quotation_uid,
            'item' => $item->fresh(['quotation', 'product', 'warehouse']),
            'reservation' => $result['reservation'],
            'preview' => $result['preview'],
        ];
    }

    private function validateQuotation(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'quote_number' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'status' => 'sometimes|string|in:draft,sent,approved,rejected,cancelled',
            'currency' => 'nullable|string|max:10',
            'exchange_rate' => 'sometimes|numeric|min:0.000001',
            'local_currency' => 'nullable|string|max:10',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string',
            'price_book_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!empty($validated['entity_type']) xor !empty($validated['entity_uid'])) {
            throw ValidationException::withMessages([
                'entity_uid' => ['Debes enviar entity_type y entity_uid juntos'],
            ]);
        }

        return $validated;
    }

    private function validateItem(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'product_uid' => 'nullable|uuid',
            'catalog_product_uid' => 'nullable|uuid',
            'warehouse_uid' => 'nullable|uuid',
            'sku' => 'nullable|string|max:255',
            'description' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:255'],
            'quantity' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'unit_price' => 'sometimes|numeric|min:0',
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'discount_amount' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!$partial && empty($validated['product_uid']) && empty($validated['catalog_product_uid'])) {
            throw ValidationException::withMessages([
                'catalog_product_uid' => ['Debes asociar un producto de inventario o un producto del catalogo'],
            ]);
        }

        if (!empty($validated['warehouse_uid']) && empty($validated['product_uid']) && empty($validated['catalog_product_uid'])) {
            throw ValidationException::withMessages([
                'product_uid' => ['Debes asociar un producto si vas a definir bodega'],
            ]);
        }

        return $validated;
    }

    private function resolveQuoteable(?string $type, ?string $uid)
    {
        if (!$type && !$uid) {
            return null;
        }

        $entity = find_entity_by_uid($type, $uid);

        if (!$entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad comercial no existe o no es visible'],
            ]);
        }

        return $entity;
    }

    private function resolveProduct(?string $uid): ?InventoryProduct
    {
        if (!$uid) {
            return null;
        }

        return InventoryProduct::query()->where('uid', $uid)->firstOr(function () {
            throw ValidationException::withMessages([
                'product_uid' => ['El producto no existe o no pertenece a este tenant'],
            ]);
        });
    }

    private function resolveCatalogProduct(?string $uid): ?Product
    {
        if (!$uid) {
            return null;
        }

        try {
            return $this->productService->findByUid($uid);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'catalog_product_uid' => ['El producto del catalogo no existe o no pertenece a este tenant'],
            ]);
        }
    }

    private function resolveWarehouse(?string $uid): ?Warehouse
    {
        if (!$uid) {
            return null;
        }

        return Warehouse::query()->where('uid', $uid)->firstOr(function () {
            throw ValidationException::withMessages([
                'warehouse_uid' => ['La bodega no existe o no pertenece a este tenant'],
            ]);
        });
    }

    private function getItemByUid(string $uid): QuotationItem
    {
        return QuotationItem::query()->with(['quotation', 'product', 'catalogProduct', 'warehouse'])->where('uid', $uid)->firstOrFail();
    }

    private function resolvePriceBook(?string $uid): ?PriceBook
    {
        if (!$uid) {
            return null;
        }

        return $this->priceBookService->resolveActivePriceBook($uid);
    }

    private function buildPricingPayload(
        ?PriceBook $priceBook,
        ?InventoryProduct $product,
        int $quantity,
        ?float $manualUnitPrice,
        float $discountPercent,
        ?float $manualDiscountAmount
    ): array {
        $listPrice = $manualUnitPrice ?? 0;
        $minMarginPercent = 0;
        $unitCost = (float) ($product?->cost_price ?? 0);

        if ($priceBook && $product) {
            $priceBookItem = $this->pricingService->resolveProductPrice($priceBook, $product);

            $listPrice = $manualUnitPrice ?? (float) $priceBookItem->unit_price;
            $minMarginPercent = (float) $priceBookItem->min_margin_percent;
        }

        $discountAmount = $manualDiscountAmount ?? round($listPrice * ($discountPercent / 100), 2);
        $netUnitPrice = max(0, round($listPrice - $discountAmount, 2));
        $marginAmount = round($netUnitPrice - $unitCost, 2);
        $marginPercent = $netUnitPrice > 0 ? round(($marginAmount / $netUnitPrice) * 100, 2) : 0;

        return [
            'list_unit_price' => $listPrice,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'net_unit_price' => $netUnitPrice,
            'unit_cost' => $unitCost,
            'margin_amount' => $marginAmount,
            'margin_percent' => $marginPercent,
            'min_margin_percent' => $minMarginPercent,
            'below_min_margin' => $marginPercent < $minMarginPercent,
            'quantity' => $quantity,
        ];
    }

    private function reservePendingQuotationStock(Quotation $quotation): void
    {
        foreach ($quotation->items()->with(['product', 'catalogProduct', 'warehouse'])->get() as $item) {
            $pendingQuantity = max(0, (int) $item->quantity - (int) $item->reserved_quantity);

            if ($pendingQuantity === 0) {
                continue;
            }

            if (!$item->product_id || !$item->warehouse_id) {
                throw ValidationException::withMessages([
                    'quotation' => ['Todos los items deben tener producto y bodega para aprobar y reservar stock'],
                ]);
            }

            $this->inventoryService->reserveStock([
                'product_uid' => $item->product_uid,
                'warehouse_uid' => $item->warehouse_uid,
                'quantity' => $pendingQuantity,
                'source_type' => 'quotation_item',
                'source_uid' => $item->uid,
                'comment' => 'Reserva automatica por aprobacion de cotizacion',
            ]);
        }
    }

    private function syncRequiredDependencies(Quotation $quotation, Product $catalogProduct, QuotationItem $sourceItem): void
    {
        $requiredProducts = $this->dependencyService->getRequiredDependencies($catalogProduct);

        foreach ($requiredProducts as $requiredProduct) {
            $alreadyPresent = $quotation->items()
                ->where('catalog_product_id', $requiredProduct->getKey())
                ->exists();

            if ($alreadyPresent) {
                continue;
            }

            $linkedInventoryProduct = $requiredProduct->inventoryProduct;
            $pricing = $this->buildPricingPayload(
                $quotation->priceBook,
                $linkedInventoryProduct,
                (int) $sourceItem->quantity,
                null,
                0,
                null
            );

            QuotationItem::query()->create([
                'quotation_id' => $quotation->getKey(),
                'product_id' => $linkedInventoryProduct?->getKey(),
                'catalog_product_id' => $requiredProduct->getKey(),
                'warehouse_id' => $sourceItem->warehouse_id,
                'sku' => $requiredProduct->sku,
                'description' => $requiredProduct->name,
                'quantity' => $sourceItem->quantity,
                'list_unit_price' => $pricing['list_unit_price'],
                'discount_percent' => 0,
                'discount_amount' => 0,
                'net_unit_price' => $pricing['net_unit_price'],
                'unit_price' => $pricing['net_unit_price'],
                'unit_cost' => $pricing['unit_cost'],
                'margin_amount' => $pricing['margin_amount'],
                'margin_percent' => $pricing['margin_percent'],
                'min_margin_percent' => $pricing['min_margin_percent'],
                'below_min_margin' => $pricing['below_min_margin'],
            ]);
        }
    }

    private function validateQuotationDependencies(Quotation $quotation): void
    {
        $catalogProducts = $quotation->items()
            ->with('catalogProduct')
            ->get()
            ->pluck('catalogProduct')
            ->filter()
            ->values();

        if ($catalogProducts->isEmpty()) {
            return;
        }

        $validation = $this->dependencyService->validateDependencies($catalogProducts);

        if (!empty($validation['required_missing']) || !empty($validation['incompatibilities'])) {
            throw ValidationException::withMessages([
                'catalog_dependencies' => [
                    'La cotizacion tiene dependencias invalidas o productos incompatibles',
                ],
                'required_missing' => $validation['required_missing'],
                'incompatibilities' => $validation['incompatibilities'],
            ]);
        }
    }
}
