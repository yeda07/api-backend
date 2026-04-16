<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class ProductVersion extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'product_id',
        'version',
        'release_date',
        'status',
        'notes',
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
        'release_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getProductUidAttribute(): ?string
    {
        return $this->product?->uid;
    }
}
