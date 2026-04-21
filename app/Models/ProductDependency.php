<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class ProductDependency extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'product_id',
        'depends_on_product_id',
        'dependency_type',
        'message',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'product_id',
        'depends_on_product_id',
    ];

    protected $appends = [
        'product_uid',
        'depends_on_product_uid',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function dependsOnProduct()
    {
        return $this->belongsTo(Product::class, 'depends_on_product_id');
    }

    public function getProductUidAttribute(): ?string
    {
        return $this->product?->uid;
    }

    public function getDependsOnProductUidAttribute(): ?string
    {
        return $this->dependsOnProduct?->uid;
    }
}
