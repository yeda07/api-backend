<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderPayment extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'purchase_order_id',
        'amount',
        'payment_date',
        'method',
        'reference',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'purchase_order_id',
    ];

    protected $appends = [
        'purchase_order_uid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'meta' => 'array',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function getPurchaseOrderUidAttribute(): ?string
    {
        return $this->purchaseOrder?->uid
            ?? ($this->purchase_order_id ? PurchaseOrder::query()->whereKey($this->purchase_order_id)->value('uid') : null);
    }
}
