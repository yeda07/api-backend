<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'inventory_product_id',
        'name',
        'type',
        'sku',
        'description',
        'status',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'inventory_product_id',
    ];

    protected $appends = [
        'inventory_product_uid',
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
}

