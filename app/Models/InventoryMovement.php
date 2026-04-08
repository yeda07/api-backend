<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'product_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'performed_by_user_id',
        'type',
        'quantity',
        'comment',
        'reference_type',
        'reference_uid',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'performed_by_user_id',
    ];

    protected $appends = [
        'product_uid',
        'from_warehouse_uid',
        'to_warehouse_uid',
        'performed_by_user_uid',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function getProductUidAttribute()
    {
        return $this->product_id
            ? InventoryProduct::query()->whereKey($this->product_id)->value('uid')
            : null;
    }

    public function getFromWarehouseUidAttribute()
    {
        return $this->from_warehouse_id
            ? Warehouse::query()->whereKey($this->from_warehouse_id)->value('uid')
            : null;
    }

    public function getToWarehouseUidAttribute()
    {
        return $this->to_warehouse_id
            ? Warehouse::query()->whereKey($this->to_warehouse_id)->value('uid')
            : null;
    }

    public function getPerformedByUserUidAttribute()
    {
        return $this->performed_by_user_id
            ? User::query()->whereKey($this->performed_by_user_id)->value('uid')
            : null;
    }
}
