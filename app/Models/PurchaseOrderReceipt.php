<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderReceipt extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'purchase_order_id',
        'receipt_date',
        'reference',
        'comment',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'purchase_order_id',
    ];

    protected $appends = [
        'purchase_order_uid',
        'total_received_quantity',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class);
    }

    public function getPurchaseOrderUidAttribute(): ?string
    {
        return $this->purchaseOrder?->uid
            ?? ($this->purchase_order_id ? PurchaseOrder::query()->whereKey($this->purchase_order_id)->value('uid') : null);
    }

    public function getTotalReceivedQuantityAttribute(): int
    {
        return (int) $this->items()->sum('received_quantity');
    }
}
