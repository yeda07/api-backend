<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class InventoryReservation extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'product_id',
        'warehouse_id',
        'reserved_by_user_id',
        'source_type',
        'source_uid',
        'quantity',
        'status',
        'comment',
        'meta',
        'released_at',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_id',
        'warehouse_id',
        'reserved_by_user_id',
    ];

    protected $appends = [
        'product_uid',
        'warehouse_uid',
        'reserved_by_user_uid',
    ];

    protected $casts = [
        'meta' => 'array',
        'released_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reservedBy()
    {
        return $this->belongsTo(User::class, 'reserved_by_user_id');
    }

    public function getProductUidAttribute()
    {
        return $this->product_id
            ? InventoryProduct::query()->whereKey($this->product_id)->value('uid')
            : null;
    }

    public function getWarehouseUidAttribute()
    {
        return $this->warehouse_id
            ? Warehouse::query()->whereKey($this->warehouse_id)->value('uid')
            : null;
    }

    public function getReservedByUserUidAttribute()
    {
        return $this->reserved_by_user_id
            ? User::query()->whereKey($this->reserved_by_user_id)->value('uid')
            : null;
    }
}
