<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'supplier_id',
        'owner_user_id',
        'source_type',
        'source_uid',
        'cost_center_id',
        'cost_center',
        'purchase_number',
        'status',
        'currency',
        'paid_total',
        'ordered_at',
        'expected_at',
        'due_date',
        'received_at',
        'closed_at',
        'notes',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'supplier_id',
        'owner_user_id',
        'cost_center_id',
    ];

    protected $appends = [
        'supplier_uid',
        'owner_user_uid',
        'cost_center_uid',
        'total',
        'received_total',
        'outstanding_total',
        'is_fully_received',
        'is_closed',
    ];

    protected $casts = [
        'paid_total' => 'decimal:2',
        'ordered_at' => 'date',
        'expected_at' => 'date',
        'due_date' => 'date',
        'received_at' => 'date',
        'closed_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchaseOrderPayment::class);
    }

    public function receipts()
    {
        return $this->hasMany(PurchaseOrderReceipt::class);
    }

    public function getSupplierUidAttribute(): ?string
    {
        return $this->supplier?->uid
            ?? ($this->supplier_id ? Supplier::query()->whereKey($this->supplier_id)->value('uid') : null);
    }

    public function getOwnerUserUidAttribute(): ?string
    {
        return $this->owner?->uid
            ?? ($this->owner_user_id ? User::query()->whereKey($this->owner_user_id)->value('uid') : null);
    }

    public function getCostCenterUidAttribute(): ?string
    {
        return $this->costCenter?->uid
            ?? ($this->cost_center_id ? CostCenter::query()->whereKey($this->cost_center_id)->value('uid') : null);
    }

    public function getTotalAttribute(): float
    {
        return round((float) $this->items()->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')->value('total'), 2);
    }

    public function getReceivedTotalAttribute(): int
    {
        return (int) $this->items()->sum('received_quantity');
    }

    public function getOutstandingTotalAttribute(): float
    {
        return round(max(0, $this->total - (float) $this->paid_total), 2);
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->isNotEmpty() && $items->every(fn (PurchaseOrderItem $item) => $item->received_quantity >= $item->quantity);
    }

    public function getIsClosedAttribute(): bool
    {
        return !is_null($this->closed_at);
    }
}
