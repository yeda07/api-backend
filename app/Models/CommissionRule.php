<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'name',
        'product_id',
        'customer_type',
        'rate_percent',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_id',
    ];

    protected $appends = [
        'product_uid',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function getProductUidAttribute()
    {
        return $this->product?->uid
            ?? ($this->product_id ? InventoryProduct::query()->whereKey($this->product_id)->value('uid') : null);
    }
}
