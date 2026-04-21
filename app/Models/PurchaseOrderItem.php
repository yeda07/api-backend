<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'purchase_order_id',
        'product_id',
        'warehouse_id',
        'description',
        'quantity',
        'unit_cost',
        'received_quantity',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'purchase_order_id',
        'product_id',
        'warehouse_id',
    ];

    protected $appends = [
        'purchase_order_uid',
        'product_uid',
        'warehouse_uid',
        'line_total',
        'pending_quantity',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'meta' => 'array',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiptItems()
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class, 'purchase_order_item_id');
    }

    public function getPurchaseOrderUidAttribute(): ?string
    {
        return $this->purchaseOrder?->uid
            ?? ($this->purchase_order_id ? PurchaseOrder::query()->whereKey($this->purchase_order_id)->value('uid') : null);
    }

    public function getProductUidAttribute(): ?string
    {
        return $this->product?->uid
            ?? ($this->product_id ? InventoryProduct::query()->whereKey($this->product_id)->value('uid') : null);
    }

    public function getWarehouseUidAttribute(): ?string
    {
        return $this->warehouse?->uid
            ?? ($this->warehouse_id ? Warehouse::query()->whereKey($this->warehouse_id)->value('uid') : null);
    }

    public function getLineTotalAttribute(): float
    {
        return round($this->quantity * (float) $this->unit_cost, 2);
    }

    public function getPendingQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity - (int) $this->received_quantity);
    }
}
