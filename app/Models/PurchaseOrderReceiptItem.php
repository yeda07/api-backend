<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderReceiptItem extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'purchase_order_receipt_id',
        'purchase_order_item_id',
        'received_quantity',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'purchase_order_receipt_id',
        'purchase_order_item_id',
    ];

    protected $appends = [
        'purchase_order_receipt_uid',
        'purchase_order_item_uid',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    public function item()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function getPurchaseOrderReceiptUidAttribute(): ?string
    {
        return $this->receipt?->uid
            ?? ($this->purchase_order_receipt_id ? PurchaseOrderReceipt::query()->whereKey($this->purchase_order_receipt_id)->value('uid') : null);
    }

    public function getPurchaseOrderItemUidAttribute(): ?string
    {
        return $this->item?->uid
            ?? ($this->purchase_order_item_id ? PurchaseOrderItem::query()->whereKey($this->purchase_order_item_id)->value('uid') : null);
    }
}
