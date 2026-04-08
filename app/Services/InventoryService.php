<?php

namespace App\Services;

use App\Models\InventoryCategory;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use App\Models\InventoryReservation;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function listCategories()
    {
        return InventoryCategory::query()->orderBy('name')->get();
    }

    public function createCategory(array $data): InventoryCategory
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255',
            'description' => 'nullable|string',
        ])->validate();

        return InventoryCategory::query()->create($validated);
    }

    public function updateCategory(string $uid, array $data): InventoryCategory
    {
        $category = $this->getCategoryByUid($uid);
        $validated = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ])->validate();

        $category->update($validated);

        return $category->fresh();
    }

    public function deleteCategory(string $uid): void
    {
        $this->getCategoryByUid($uid)->delete();
    }

    public function listProducts()
    {
        return InventoryProduct::query()
            ->with(['category', 'stocks.warehouse'])
            ->orderBy('name')
            ->get();
    }

    public function createProduct(array $data): InventoryProduct
    {
        $validated = $this->validateProduct($data);

        return DB::transaction(function () use ($validated) {
            $product = InventoryProduct::query()->create([
                'category_id' => $this->resolveCategoryId($validated['category_uid'] ?? null),
                'sku' => $validated['sku'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'reorder_point' => $validated['reorder_point'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->syncWarehouseStocks($product, $validated['warehouse_stocks'] ?? []);

            return $product->fresh(['category', 'stocks.warehouse']);
        });
    }

    public function updateProduct(string $uid, array $data): InventoryProduct
    {
        $product = $this->getProductByUid($uid);
        $validated = $this->validateProduct($data, true);

        return DB::transaction(function () use ($product, $validated) {
            $payload = [];

            if (array_key_exists('category_uid', $validated)) {
                $payload['category_id'] = $this->resolveCategoryId($validated['category_uid']);
            }

            foreach (['sku', 'name', 'description', 'reorder_point', 'is_active'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            if ($payload !== []) {
                $product->update($payload);
            }

            if (array_key_exists('warehouse_stocks', $validated)) {
                $this->syncWarehouseStocks($product, $validated['warehouse_stocks'], true);
            }

            return $product->fresh(['category', 'stocks.warehouse']);
        });
    }

    public function deleteProduct(string $uid): void
    {
        $this->getProductByUid($uid)->delete();
    }

    public function listWarehouses()
    {
        return Warehouse::query()->withCount('stocks')->orderBy('name')->get();
    }

    public function createWarehouse(array $data): Warehouse
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        return Warehouse::query()->create($validated);
    }

    public function updateWarehouse(string $uid, array $data): Warehouse
    {
        $warehouse = $this->getWarehouseByUid($uid);
        $validated = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $warehouse->update($validated);

        return $warehouse->fresh();
    }

    public function deleteWarehouse(string $uid): void
    {
        $warehouse = $this->getWarehouseByUid($uid);

        if ($warehouse->stocks()->where('physical_stock', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'warehouse' => ['No puedes eliminar una bodega con stock fisico'],
            ]);
        }

        $warehouse->delete();
    }

    public function master(array $filters): array
    {
        $validated = Validator::make($filters, [
            'category_uid' => 'nullable|uuid',
            'warehouse_uid' => 'nullable|uuid',
            'stock_state' => 'nullable|string|in:normal,low,out',
        ])->validate();

        $warehouse = !empty($validated['warehouse_uid'])
            ? $this->getWarehouseByUid($validated['warehouse_uid'])
            : null;

        $products = InventoryProduct::query()
            ->with(['category', 'stocks.warehouse'])
            ->when(!empty($validated['category_uid']), function ($query) use ($validated) {
                $query->where('category_id', $this->resolveCategoryId($validated['category_uid']) ?: 0);
            })
            ->orderBy('name')
            ->get();

        $rows = $products
            ->map(fn (InventoryProduct $product) => $this->masterRow($product, $warehouse))
            ->when(!empty($validated['stock_state']), fn (Collection $collection) => $collection->where('stock_state', $validated['stock_state']))
            ->values();

        return [
            'filters' => [
                'category_uid' => $validated['category_uid'] ?? null,
                'warehouse_uid' => $validated['warehouse_uid'] ?? null,
                'stock_state' => $validated['stock_state'] ?? null,
            ],
            'data' => $rows,
            'summary' => [
                'products' => $rows->count(),
                'total_physical_stock' => (int) $rows->sum('stock_physical_total'),
                'total_reserved_stock' => (int) $rows->sum('stock_reserved_total'),
                'total_available_stock' => (int) $rows->sum('stock_available_total'),
            ],
        ];
    }

    public function warehouseStocks(string $warehouseUid): array
    {
        $warehouse = $this->getWarehouseByUid($warehouseUid);

        $products = InventoryProduct::query()
            ->with(['category', 'stocks' => fn ($query) => $query->where('warehouse_id', $warehouse->getKey())->with('warehouse')])
            ->orderBy('name')
            ->get();

        return [
            'warehouse' => $warehouse,
            'data' => $products->map(fn (InventoryProduct $product) => $this->masterRow($product, $warehouse))->values(),
        ];
    }

    public function adjustStock(array $data): array
    {
        $validated = Validator::make($data, [
            'product_uid' => 'required|uuid',
            'warehouse_uid' => 'required|uuid',
            'operation' => 'required|string|in:in,out,set',
            'quantity' => 'required|integer|min:0',
            'comment' => 'nullable|string',
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $product = $this->getProductByUid($validated['product_uid']);
            $warehouse = $this->getWarehouseByUid($validated['warehouse_uid']);
            $stock = $this->getOrCreateStock($product, $warehouse);
            $previousPhysical = (int) $stock->physical_stock;

            $projectedPhysical = match ($validated['operation']) {
                'in' => $previousPhysical + (int) $validated['quantity'],
                'out' => $previousPhysical - (int) $validated['quantity'],
                default => (int) $validated['quantity'],
            };

            if ($projectedPhysical < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['El stock fisico no puede quedar negativo'],
                ]);
            }

            if ($projectedPhysical < (int) $stock->reserved_stock) {
                throw ValidationException::withMessages([
                    'quantity' => ['El stock fisico no puede quedar por debajo del stock reservado'],
                ]);
            }

            $stock->update(['physical_stock' => $projectedPhysical]);

            $movement = $this->recordMovement([
                'product_id' => $product->getKey(),
                'from_warehouse_id' => $validated['operation'] === 'out' ? $warehouse->getKey() : null,
                'to_warehouse_id' => $validated['operation'] === 'in' || $validated['operation'] === 'set' ? $warehouse->getKey() : null,
                'type' => match ($validated['operation']) {
                    'in' => 'adjustment_in',
                    'out' => 'adjustment_out',
                    default => 'set_balance',
                },
                'quantity' => abs($projectedPhysical - $previousPhysical),
                'comment' => $validated['comment'] ?? null,
                'meta' => [
                    'previous_physical_stock' => $previousPhysical,
                    'projected_physical_stock' => $projectedPhysical,
                    'reserved_stock' => (int) $stock->reserved_stock,
                ],
            ]);

            return [
                'movement' => $movement,
                'stock' => $stock->fresh(['product', 'warehouse']),
                'preview' => [
                    'physical_stock' => $previousPhysical,
                    'reserved_stock' => (int) $stock->reserved_stock,
                    'available_stock' => max(0, $previousPhysical - (int) $stock->reserved_stock),
                    'projected_physical_stock' => $projectedPhysical,
                    'projected_available_stock' => max(0, $projectedPhysical - (int) $stock->reserved_stock),
                ],
            ];
        });
    }

    public function reserveStock(array $data): array
    {
        $validated = Validator::make($data, [
            'product_uid' => 'required|uuid',
            'warehouse_uid' => 'required|uuid',
            'quantity' => 'required|integer|min:1',
            'source_type' => 'required|string|max:255',
            'source_uid' => 'required|string|max:255',
            'comment' => 'nullable|string',
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $product = $this->getProductByUid($validated['product_uid']);
            $warehouse = $this->getWarehouseByUid($validated['warehouse_uid']);
            $stock = $this->getOrCreateStock($product, $warehouse);
            $preview = $this->reservationPreview($stock, (int) $validated['quantity']);

            if ($preview['exceeds_available']) {
                throw ValidationException::withMessages([
                    'quantity' => ['La reserva excede el stock disponible'],
                ]);
            }

            $reservation = InventoryReservation::query()->create([
                'product_id' => $product->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'reserved_by_user_id' => auth()->id(),
                'source_type' => $validated['source_type'],
                'source_uid' => $validated['source_uid'],
                'quantity' => (int) $validated['quantity'],
                'comment' => $validated['comment'] ?? null,
                'status' => 'active',
                'meta' => [
                    'preview' => $preview,
                ],
            ]);

            $stock->increment('reserved_stock', (int) $validated['quantity']);

            $movement = $this->recordMovement([
                'product_id' => $product->getKey(),
                'from_warehouse_id' => $warehouse->getKey(),
                'type' => 'reservation',
                'quantity' => (int) $validated['quantity'],
                'comment' => $validated['comment'] ?? null,
                'reference_type' => $validated['source_type'],
                'reference_uid' => $validated['source_uid'],
                'meta' => $preview,
            ]);

            return [
                'reservation' => $reservation->fresh(['product', 'warehouse', 'reservedBy']),
                'movement' => $movement,
                'preview' => $this->reservationPreview($stock->fresh(), (int) $validated['quantity']),
            ];
        });
    }

    public function releaseReservation(string $uid): array
    {
        return DB::transaction(function () use ($uid) {
            $reservation = InventoryReservation::query()->with(['product', 'warehouse', 'reservedBy'])->where('uid', $uid)->firstOrFail();

            if ($reservation->status !== 'active') {
                throw ValidationException::withMessages([
                    'reservation' => ['La reserva ya no esta activa'],
                ]);
            }

            $stock = InventoryStock::query()
                ->where('product_id', $reservation->product_id)
                ->where('warehouse_id', $reservation->warehouse_id)
                ->firstOrFail();

            $stock->update([
                'reserved_stock' => max(0, (int) $stock->reserved_stock - (int) $reservation->quantity),
            ]);

            $reservation->update([
                'status' => 'released',
                'released_at' => now(),
            ]);

            $movement = $this->recordMovement([
                'product_id' => $reservation->product_id,
                'to_warehouse_id' => $reservation->warehouse_id,
                'type' => 'reservation_release',
                'quantity' => (int) $reservation->quantity,
                'comment' => $reservation->comment,
                'reference_type' => $reservation->source_type,
                'reference_uid' => $reservation->source_uid,
            ]);

            return [
                'reservation' => $reservation->fresh(['product', 'warehouse', 'reservedBy']),
                'movement' => $movement,
                'preview' => $this->reservationPreview($stock->fresh(), 0),
            ];
        });
    }

    public function reservationsBySource(string $sourceType, string $sourceUid): array
    {
        $reservations = InventoryReservation::query()
            ->with(['product', 'warehouse', 'reservedBy'])
            ->where('source_type', $sourceType)
            ->where('source_uid', $sourceUid)
            ->latest()
            ->get();

        return [
            'source_type' => $sourceType,
            'source_uid' => $sourceUid,
            'reservations' => $reservations,
            'totals' => [
                'reserved_units' => (int) $reservations->where('status', 'active')->sum('quantity'),
                'active_reservations' => (int) $reservations->where('status', 'active')->count(),
            ],
        ];
    }

    public function transferStock(array $data): array
    {
        $validated = Validator::make($data, [
            'product_uid' => 'required|uuid',
            'from_warehouse_uid' => 'required|uuid|different:to_warehouse_uid',
            'to_warehouse_uid' => 'required|uuid',
            'quantity' => 'required|integer|min:1',
            'comment' => 'nullable|string',
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $product = $this->getProductByUid($validated['product_uid']);
            $from = $this->getWarehouseByUid($validated['from_warehouse_uid']);
            $to = $this->getWarehouseByUid($validated['to_warehouse_uid']);
            $fromStock = $this->getOrCreateStock($product, $from);
            $toStock = $this->getOrCreateStock($product, $to);

            $fromBefore = (int) $fromStock->physical_stock;
            $toBefore = (int) $toStock->physical_stock;
            $available = max(0, $fromBefore - (int) $fromStock->reserved_stock);
            $projectedFrom = $fromBefore - (int) $validated['quantity'];
            $projectedTo = $toBefore + (int) $validated['quantity'];

            if ($available < (int) $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => ['La transferencia excede el stock disponible en origen'],
                ]);
            }

            $fromStock->update(['physical_stock' => $projectedFrom]);
            $toStock->update(['physical_stock' => $projectedTo]);

            $movement = $this->recordMovement([
                'product_id' => $product->getKey(),
                'from_warehouse_id' => $from->getKey(),
                'to_warehouse_id' => $to->getKey(),
                'type' => 'transfer',
                'quantity' => (int) $validated['quantity'],
                'comment' => $validated['comment'] ?? null,
                'meta' => [
                    'from_previous_physical_stock' => $fromBefore,
                    'from_projected_physical_stock' => $projectedFrom,
                    'to_previous_physical_stock' => $toBefore,
                    'to_projected_physical_stock' => $projectedTo,
                ],
            ]);

            return [
                'movement' => $movement,
                'preview' => [
                    'product_uid' => $product->uid,
                    'from_warehouse_uid' => $from->uid,
                    'to_warehouse_uid' => $to->uid,
                    'quantity' => (int) $validated['quantity'],
                    'from' => [
                        'physical_stock' => $fromBefore,
                        'reserved_stock' => (int) $fromStock->reserved_stock,
                        'available_stock' => $available,
                        'projected_physical_stock' => $projectedFrom,
                        'projected_available_stock' => max(0, $projectedFrom - (int) $fromStock->reserved_stock),
                    ],
                    'to' => [
                        'physical_stock' => $toBefore,
                        'reserved_stock' => (int) $toStock->reserved_stock,
                        'available_stock' => max(0, $toBefore - (int) $toStock->reserved_stock),
                        'projected_physical_stock' => $projectedTo,
                        'projected_available_stock' => max(0, $projectedTo - (int) $toStock->reserved_stock),
                    ],
                ],
            ];
        });
    }

    public function report(array $filters): array
    {
        $master = $this->master($filters);
        $rows = collect($master['data']);
        $summaryByCategory = $rows
            ->groupBy(fn (array $row) => $row['category_name'] ?? 'Sin categoria')
            ->map(fn (Collection $group, string $category) => [
                'category' => $category,
                'products' => $group->count(),
                'physical_stock' => (int) $group->sum('stock_physical_total'),
                'reserved_stock' => (int) $group->sum('stock_reserved_total'),
                'available_stock' => (int) $group->sum('stock_available_total'),
            ])
            ->values();

        $criticalProducts = $rows->whereIn('stock_state', ['low', 'out'])->values();
        $criticalCount = $criticalProducts->count();
        $totalProducts = max(1, $rows->count());
        $criticalRatio = round(($criticalCount / $totalProducts) * 100, 2);

        return [
            'filters' => $master['filters'],
            'summary_by_category' => $summaryByCategory,
            'critical_products' => $criticalProducts,
            'rupture_risk' => [
                'critical_products_count' => $criticalCount,
                'out_of_stock_count' => $rows->where('stock_state', 'out')->count(),
                'low_stock_count' => $rows->where('stock_state', 'low')->count(),
                'risk_percentage' => $criticalRatio,
                'risk_level' => match (true) {
                    $criticalRatio >= 60 => 'high',
                    $criticalRatio >= 25 => 'medium',
                    default => 'low',
                },
            ],
        ];
    }

    public function reportAsCsv(array $filters): string
    {
        $rows = collect($this->master($filters)['data']);
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'sku',
            'product',
            'category',
            'warehouse_uid',
            'stock_physical_total',
            'stock_reserved_total',
            'stock_available_total',
            'stock_state',
            'stock_indicator',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['sku'],
                $row['product'],
                $row['category_name'],
                $row['warehouse_uid'],
                $row['stock_physical_total'],
                $row['stock_reserved_total'],
                $row['stock_available_total'],
                $row['stock_state'],
                $row['stock_indicator'],
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv ?: '';
    }

    private function validateProduct(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'category_uid' => 'nullable|uuid',
            'sku' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'reorder_point' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'warehouse_stocks' => 'sometimes|array',
            'warehouse_stocks.*.warehouse_uid' => 'required_with:warehouse_stocks|uuid',
            'warehouse_stocks.*.physical_stock' => 'required_with:warehouse_stocks|integer|min:0',
        ])->validate();
    }

    private function syncWarehouseStocks(InventoryProduct $product, array $warehouseStocks, bool $updating = false): void
    {
        foreach ($warehouseStocks as $stockPayload) {
            $warehouse = $this->getWarehouseByUid($stockPayload['warehouse_uid']);
            $stock = $this->getOrCreateStock($product, $warehouse);

            if ($updating && (int) $stock->reserved_stock > (int) $stockPayload['physical_stock']) {
                throw ValidationException::withMessages([
                    'warehouse_stocks' => ['El stock fisico no puede quedar por debajo del stock reservado'],
                ]);
            }

            $stock->update([
                'physical_stock' => (int) $stockPayload['physical_stock'],
            ]);
        }
    }

    private function getCategoryByUid(string $uid): InventoryCategory
    {
        return InventoryCategory::query()->where('uid', $uid)->firstOrFail();
    }

    private function getProductByUid(string $uid): InventoryProduct
    {
        return InventoryProduct::query()->with(['category', 'stocks.warehouse'])->where('uid', $uid)->firstOrFail();
    }

    private function getWarehouseByUid(string $uid): Warehouse
    {
        return Warehouse::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveCategoryId(?string $uid): ?int
    {
        if (!$uid) {
            return null;
        }

        $categoryId = InventoryCategory::query()->where('uid', $uid)->value('id');

        if (!$categoryId) {
            throw ValidationException::withMessages([
                'category_uid' => ['La categoria no existe o no pertenece a este tenant'],
            ]);
        }

        return $categoryId;
    }

    private function getOrCreateStock(InventoryProduct $product, Warehouse $warehouse): InventoryStock
    {
        return InventoryStock::query()->firstOrCreate(
            [
                'product_id' => $product->getKey(),
                'warehouse_id' => $warehouse->getKey(),
            ],
            [
                'physical_stock' => 0,
                'reserved_stock' => 0,
            ]
        );
    }

    private function recordMovement(array $payload): InventoryMovement
    {
        return InventoryMovement::query()->create([
            'product_id' => $payload['product_id'],
            'from_warehouse_id' => $payload['from_warehouse_id'] ?? null,
            'to_warehouse_id' => $payload['to_warehouse_id'] ?? null,
            'performed_by_user_id' => auth()->id(),
            'type' => $payload['type'],
            'quantity' => $payload['quantity'],
            'comment' => $payload['comment'] ?? null,
            'reference_type' => $payload['reference_type'] ?? null,
            'reference_uid' => $payload['reference_uid'] ?? null,
            'meta' => $payload['meta'] ?? null,
        ])->fresh(['product', 'fromWarehouse', 'toWarehouse', 'performedBy']);
    }

    private function reservationPreview(InventoryStock $stock, int $requestedQuantity): array
    {
        $physical = (int) $stock->physical_stock;
        $reserved = (int) $stock->reserved_stock;
        $available = max(0, $physical - $reserved);
        $projectedReserved = $reserved + $requestedQuantity;
        $projectedAvailable = $physical - $projectedReserved;

        return [
            'stock_actual' => $physical,
            'stock_reservado_actual' => $reserved,
            'stock_disponible' => $available,
            'unidades_a_reservar' => $requestedQuantity,
            'resultado_final_proyectado' => $projectedAvailable,
            'exceeds_available' => $projectedAvailable < 0,
        ];
    }

    private function masterRow(InventoryProduct $product, ?Warehouse $warehouse = null): array
    {
        $stocks = $warehouse
            ? $product->stocks->where('warehouse_id', $warehouse->getKey())
            : $product->stocks;

        $physical = (int) $stocks->sum('physical_stock');
        $reserved = (int) $stocks->sum('reserved_stock');
        $available = max(0, $physical - $reserved);
        $state = match (true) {
            $available <= 0 => 'out',
            $available <= (int) $product->reorder_point => 'low',
            default => 'normal',
        };

        return [
            'uid' => $product->uid,
            'sku' => $product->sku,
            'product' => $product->name,
            'category_uid' => $product->category?->uid,
            'category_name' => $product->category?->name,
            'warehouse_uid' => $warehouse?->uid,
            'stock_physical_total' => $physical,
            'stock_reserved_total' => $reserved,
            'stock_available_total' => $available,
            'stock_state' => $state,
            'stock_indicator' => match ($state) {
                'out' => 'red',
                'low' => 'yellow',
                default => 'green',
            },
            'reorder_point' => (int) $product->reorder_point,
        ];
    }
}
