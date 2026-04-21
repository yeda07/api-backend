<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class InventoryProduct extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'category_id',
        'sku',
        'name',
        'description',
        'cost_price',
        'reorder_point',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'category_id',
    ];

    protected $appends = [
        'category_uid',
        'category_name',
        'stock_physical_total',
        'stock_reserved_total',
        'stock_available_total',
        'stock_state',
        'stock_indicator',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cost_price' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function stocks()
    {
        return $this->hasMany(InventoryStock::class, 'product_id');
    }

    public function reservations()
    {
        return $this->hasMany(InventoryReservation::class, 'product_id');
    }

    public function getCategoryUidAttribute()
    {
        return $this->category?->uid
            ?? ($this->category_id ? InventoryCategory::query()->whereKey($this->category_id)->value('uid') : null);
    }

    public function getCategoryNameAttribute()
    {
        return $this->category?->name
            ?? ($this->category_id ? InventoryCategory::query()->whereKey($this->category_id)->value('name') : null);
    }

    public function getStockPhysicalTotalAttribute(): int
    {
        return (int) $this->stocks()->sum('physical_stock');
    }

    public function getStockReservedTotalAttribute(): int
    {
        return (int) $this->stocks()->sum('reserved_stock');
    }

    public function getStockAvailableTotalAttribute(): int
    {
        return max(0, $this->stock_physical_total - $this->stock_reserved_total);
    }

    public function getStockStateAttribute(): string
    {
        return match (true) {
            $this->stock_available_total <= 0 => 'out',
            $this->stock_available_total <= (int) $this->reorder_point => 'low',
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
