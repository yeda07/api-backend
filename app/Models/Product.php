<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\HasCustomFieldValues;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasCustomFieldValues, HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'inventory_product_id',
        'name',
        'type',
        'sku',
        'description',
        'status',
        'default_price',
        'default_discount_percent',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'inventory_product_id',
    ];

    protected $appends = [
        'inventory_product_uid',
    ];

    protected $casts = [
        'default_price' => 'float',
        'default_discount_percent' => 'float',
    ];

    public function inventoryProduct()
    {
        return $this->belongsTo(InventoryProduct::class, 'inventory_product_id');
    }

    public function versions()
    {
        return $this->hasMany(ProductVersion::class);
    }

    public function dependencies()
    {
        return $this->hasMany(ProductDependency::class);
    }

    public function dependents()
    {
        return $this->hasMany(ProductDependency::class, 'depends_on_product_id');
    }

    public function installedAccounts()
    {
        return $this->hasMany(AccountProduct::class);
    }

    public function getInventoryProductUidAttribute(): ?string
    {
        return $this->inventoryProduct?->uid;
    }

    public function getDefaultPriceAttribute($value): ?float
    {
        if ($value !== null) {
            return round((float) $value, 2);
        }

        $inventoryPrice = $this->inventoryProduct?->sale_price;

        return $inventoryPrice !== null ? round((float) $inventoryPrice, 2) : null;
    }

    public function getDefaultDiscountPercentAttribute($value): float
    {
        if ($value !== null) {
            return round((float) $value, 2);
        }

        return round((float) ($this->inventoryProduct?->discount_percent ?? 0), 2);
    }
}
