<?php

namespace App\Services;

use App\Models\InventoryProduct;
use App\Models\PurchaseOrderPayment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\CostCenter;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'status' => 'nullable|string|in:draft,approved,partial_received,received,cancelled,partial_paid,paid,overdue',
            'supplier_uid' => 'nullable|uuid',
            'cost_center_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'source_type' => 'nullable|string|max:255',
            'source_uid' => 'nullable|uuid',
        ])->validate();

        $query = PurchaseOrder::query()->with(['supplier', 'owner', 'costCenter', 'items.product', 'items.warehouse', 'payments', 'receipts.items'])->latest('ordered_at');

        if (!empty($validated['supplier_uid'])) {
            $query->where('supplier_id', $this->resolveSupplier($validated['supplier_uid'])->getKey());
        }

        if (!empty($validated['cost_center_uid'])) {
            $query->where('cost_center_id', $this->resolveCostCenter($validated['cost_center_uid'])->getKey());
        }

        if (!empty($validated['entity_type']) || !empty($validated['entity_uid'])) {
            $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $query->where('source_type', get_class($entity))
                ->where('source_uid', $entity->uid);
        }

        foreach (['status', 'source_type', 'source_uid'] as $field) {
            if (!empty($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        return $query->get();
    }

    public function show(string $uid): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'owner', 'costCenter', 'items.product', 'items.warehouse', 'payments'])
            ->with(['receipts.items'])
            ->where('uid', $uid)
            ->firstOrFail();
    }

    public function create(array $data): PurchaseOrder
    {
        $validated = $this->validatePurchaseOrder($data);
        $entity = $this->resolveOptionalEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);

        return DB::transaction(function () use ($validated, $entity) {
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $this->resolveSupplier($validated['supplier_uid'])->getKey(),
                'owner_user_id' => !empty($validated['owner_user_uid']) ? $this->resolveUser($validated['owner_user_uid'])->getKey() : auth()->id(),
                'source_type' => $entity ? get_class($entity) : ($validated['source_type'] ?? null),
                'source_uid' => $entity?->uid ?? ($validated['source_uid'] ?? null),
                'cost_center_id' => !empty($validated['cost_center_uid']) ? $this->resolveCostCenter($validated['cost_center_uid'])->getKey() : null,
                'cost_center' => $validated['cost_center'] ?? null,
                'purchase_number' => $validated['purchase_number'],
                'status' => $validated['status'] ?? 'draft',
                'currency' => $validated['currency'] ?? 'COP',
                'paid_total' => 0,
                'ordered_at' => $validated['ordered_at'] ?? null,
                'expected_at' => $validated['expected_at'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $product = !empty($item['product_uid']) ? $this->resolveProduct($item['product_uid']) : null;
                $warehouse = !empty($item['warehouse_uid']) ? $this->resolveWarehouse($item['warehouse_uid']) : null;

                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'product_id' => $product?->getKey(),
                    'warehouse_id' => $warehouse?->getKey(),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'meta' => $item['meta'] ?? null,
                ]);
            }

            return $this->show($order->uid);
        });
    }

    public function update(string $uid, array $data): PurchaseOrder
    {
        $order = PurchaseOrder::query()->where('uid', $uid)->firstOrFail();
        $validated = $this->validatePurchaseOrder($data, true);
        $payload = [];

        if (array_key_exists('supplier_uid', $validated)) {
            $payload['supplier_id'] = $this->resolveSupplier($validated['supplier_uid'])->getKey();
        }

        if (array_key_exists('owner_user_uid', $validated)) {
            $payload['owner_user_id'] = $validated['owner_user_uid'] ? $this->resolveUser($validated['owner_user_uid'])->getKey() : null;
        }

        if (array_key_exists('cost_center_uid', $validated)) {
            $payload['cost_center_id'] = $validated['cost_center_uid'] ? $this->resolveCostCenter($validated['cost_center_uid'])->getKey() : null;
        }

        if (array_key_exists('entity_type', $validated) || array_key_exists('entity_uid', $validated)) {
            $entity = $this->resolveOptionalEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $payload['source_type'] = $entity ? get_class($entity) : null;
            $payload['source_uid'] = $entity?->uid;
        }

        foreach (['source_type', 'source_uid', 'cost_center', 'purchase_number', 'status', 'currency', 'ordered_at', 'expected_at', 'due_date', 'notes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        return DB::transaction(function () use ($order, $payload, $validated) {
            if (!empty($payload)) {
                $order->update($payload);
            }

            if (array_key_exists('items', $validated) && $order->status === 'draft') {
                $order->items()->delete();

                foreach ($validated['items'] as $item) {
                    $product = !empty($item['product_uid']) ? $this->resolveProduct($item['product_uid']) : null;
                    $warehouse = !empty($item['warehouse_uid']) ? $this->resolveWarehouse($item['warehouse_uid']) : null;

                    $order->items()->create([
                        'tenant_id' => $order->tenant_id,
                        'product_id' => $product?->getKey(),
                        'warehouse_id' => $warehouse?->getKey(),
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'meta' => $item['meta'] ?? null,
                    ]);
                }
            }

            return $this->show($order->uid);
        });
    }

    public function approve(string $uid): PurchaseOrder
    {
        $order = PurchaseOrder::query()->where('uid', $uid)->firstOrFail();
        $order->update([
            'status' => 'approved',
            'ordered_at' => $order->ordered_at ?: now()->toDateString(),
            'due_date' => $order->due_date ?: now()->addDays((int) ($order->supplier?->payment_terms_days ?? 0))->toDateString(),
        ]);

        return $this->show($order->uid);
    }

    public function receivePartial(string $uid, array $data): PurchaseOrder
    {
        $order = $this->show($uid);
        $validated = Validator::make($data, [
            'receipt_date' => 'nullable|date',
            'reference' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_uid' => 'required|uuid',
            'items.*.received_quantity' => 'required|integer|min:1',
        ])->validate();

        return DB::transaction(function () use ($order, $validated) {
            $receipt = PurchaseOrderReceipt::query()->create([
                'tenant_id' => $order->tenant_id,
                'purchase_order_id' => $order->getKey(),
                'receipt_date' => $validated['receipt_date'] ?? now()->toDateString(),
                'reference' => $validated['reference'] ?? null,
                'comment' => $validated['comment'] ?? null,
            ]);

            foreach ($validated['items'] as $receivedItem) {
                $item = $order->items->firstWhere('uid', $receivedItem['item_uid']);

                if (!$item) {
                    throw ValidationException::withMessages([
                        'items' => ['Uno de los items no pertenece a la orden de compra'],
                    ]);
                }

                $pendingQuantity = $item->quantity - $item->received_quantity;
                $quantityToReceive = (int) $receivedItem['received_quantity'];

                if ($quantityToReceive > $pendingQuantity) {
                    throw ValidationException::withMessages([
                        'items' => ['La recepcion parcial excede la cantidad pendiente del item'],
                    ]);
                }

                if ($item->product_uid && $item->warehouse_uid) {
                    $this->inventoryService->adjustStock([
                        'product_uid' => $item->product_uid,
                        'warehouse_uid' => $item->warehouse_uid,
                        'operation' => 'in',
                        'quantity' => $quantityToReceive,
                        'comment' => 'Ingreso por compra ' . $order->purchase_number,
                    ]);
                }

                $item->update(['received_quantity' => $item->received_quantity + $quantityToReceive]);

                $receipt->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'purchase_order_item_id' => $item->getKey(),
                    'received_quantity' => $quantityToReceive,
                ]);
            }

            $this->syncOrderStatus($order->fresh());

            return $this->show($order->uid);
        });
    }

    public function markReceived(string $uid): PurchaseOrder
    {
        $order = $this->show($uid);

        return $this->receivePartial($uid, [
            'comment' => 'Recepcion completa de la orden',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'item_uid' => $item->uid,
                'received_quantity' => max(0, $item->quantity - $item->received_quantity),
            ])->filter(fn (array $item) => $item['received_quantity'] > 0)->values()->all(),
        ]);
    }

    public function registerPayment(string $uid, array $data): PurchaseOrder
    {
        $order = $this->show($uid);
        $validated = Validator::make($data, [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'meta' => 'nullable|array',
        ])->validate();

        if ((float) $validated['amount'] > $order->outstanding_total) {
            throw ValidationException::withMessages([
                'amount' => ['El pago no puede ser mayor al saldo pendiente de la orden'],
            ]);
        }

        return DB::transaction(function () use ($order, $validated) {
            PurchaseOrderPayment::query()->create([
                'tenant_id' => $order->tenant_id,
                'purchase_order_id' => $order->getKey(),
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'method' => $validated['method'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'meta' => $validated['meta'] ?? null,
            ]);

            $paidTotal = round((float) $order->payments()->sum('amount'), 2);
            $order->update(['paid_total' => $paidTotal]);
            $this->syncOrderStatus($order->fresh());

            return $this->show($order->uid);
        });
    }

    public function receipts(string $uid)
    {
        $order = $this->show($uid);

        return $order->receipts()
            ->with(['items.item.product', 'items.item.warehouse'])
            ->latest('receipt_date')
            ->get();
    }

    public function payables(array $filters = []): array
    {
        $validated = Validator::make($filters, [
            'supplier_uid' => 'nullable|uuid',
            'status' => 'nullable|string|in:approved,partial_received,received,partial_paid,overdue',
        ])->validate();

        $query = PurchaseOrder::query()
            ->with(['supplier', 'costCenter', 'payments', 'items'])
            ->whereNotIn('status', ['draft', 'cancelled', 'paid']);

        if (!empty($validated['supplier_uid'])) {
            $query->where('supplier_id', $this->resolveSupplier($validated['supplier_uid'])->getKey());
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $orders = $query->get()->map(function (PurchaseOrder $order) {
            if ($order->status !== 'paid'
                && $order->due_date
                && now()->startOfDay()->gt($order->due_date->copy()->startOfDay())
                && $order->outstanding_total > 0) {
                $order->status = 'overdue';
            }

            return $order;
        });

        return [
            'summary' => [
                'orders_count' => $orders->count(),
                'outstanding_total' => round((float) $orders->sum(fn (PurchaseOrder $order) => $order->outstanding_total), 2),
                'overdue_count' => $orders->where('status', 'overdue')->count(),
            ],
            'by_supplier' => $orders->groupBy(fn (PurchaseOrder $order) => $order->supplier?->name ?? 'Sin proveedor')
                ->map(fn ($group, $supplier) => [
                    'supplier' => $supplier,
                    'orders' => $group->count(),
                    'outstanding_total' => round((float) $group->sum(fn (PurchaseOrder $order) => $order->outstanding_total), 2),
                ])->values(),
            'orders' => $orders->values(),
        ];
    }

    private function syncOrderStatus(PurchaseOrder $order): void
    {
        $items = $order->items()->get();
        $allReceived = $items->isNotEmpty() && $items->every(fn (PurchaseOrderItem $item) => $item->received_quantity >= $item->quantity);
        $anyReceived = $items->contains(fn (PurchaseOrderItem $item) => $item->received_quantity > 0);
        $outstanding = max(0, $order->total - (float) $order->paid_total);

        $status = match (true) {
            $outstanding <= 0 => 'paid',
            $order->due_date && now()->startOfDay()->gt($order->due_date->copy()->startOfDay()) => 'overdue',
            (float) $order->paid_total > 0 => 'partial_paid',
            $allReceived => 'received',
            $anyReceived => 'partial_received',
            default => 'approved',
        };

        $order->update([
            'status' => $status,
            'received_at' => $anyReceived ? now()->toDateString() : null,
            'closed_at' => ($allReceived && $outstanding <= 0) ? now() : null,
        ]);
    }

    private function validatePurchaseOrder(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'supplier_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'owner_user_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'source_type' => 'nullable|string|max:255',
            'source_uid' => 'nullable|uuid',
            'cost_center_uid' => 'nullable|uuid',
            'cost_center' => 'nullable|string|max:255',
            'purchase_number' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'status' => 'sometimes|string|in:draft,approved,partial_received,received,cancelled,partial_paid,paid,overdue',
            'currency' => 'sometimes|string|max:10',
            'ordered_at' => 'nullable|date',
            'expected_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'items.*.product_uid' => 'nullable|uuid',
            'items.*.warehouse_uid' => 'nullable|uuid',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.meta' => 'nullable|array',
        ])->validate();
    }

    private function resolveSupplier(string $uid): Supplier
    {
        return Supplier::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveUser(string $uid): User
    {
        return User::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveProduct(string $uid): InventoryProduct
    {
        return InventoryProduct::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveWarehouse(string $uid): Warehouse
    {
        return Warehouse::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveCostCenter(string $uid): CostCenter
    {
        return CostCenter::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveEntity(?string $type, ?string $uid)
    {
        if (!$type || !$uid) {
            throw ValidationException::withMessages([
                'entity_uid' => ['Debes enviar entity_type y entity_uid'],
            ]);
        }

        $entity = find_entity_by_uid($type, $uid);

        if (!$entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad asociada no existe o no es visible'],
            ]);
        }

        return $entity;
    }

    private function resolveOptionalEntity(?string $type, ?string $uid)
    {
        if (!$type && !$uid) {
            return null;
        }

        return $this->resolveEntity($type, $uid);
    }
}
