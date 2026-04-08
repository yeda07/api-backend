<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class InventoryStock extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'product_id',
        'warehouse_id',
        'physical_stock',
        'reserved_stock',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_id',
        'warehouse_id',
    ];

    protected $appends = [
        'product_uid',
        'warehouse_uid',
        'available_stock',
        'stock_state',
        'stock_indicator',
    ];

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
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

    public function getAvailableStockAttribute(): int
    {
        return max(0, (int) $this->physical_stock - (int) $this->reserved_stock);
    }

    public function getStockStateAttribute(): string
    {
        $reorderPoint = $this->product_id
            ? (int) InventoryProduct::query()->whereKey($this->product_id)->value('reorder_point')
            : 0;

        return match (true) {
            $this->available_stock <= 0 => 'out',
            $this->available_stock <= $reorderPoint => 'low',
            default => 'normal',
        };
    }

    public function getStockIndicatorAttribute(): string
    {
        return match ($this->stock_state) {
            'out' => 'red',
            'low' => 'yellow',
            default => 'green',
        };
    }
}
