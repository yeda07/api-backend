<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class AccountProduct extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'account_id',
        'product_id',
        'product_version_id',
        'installed_at',
        'status',
        'notes',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'account_id',
        'product_id',
        'product_version_id',
    ];

    protected $appends = [
        'account_uid',
        'product_uid',
        'product_version_uid',
    ];

    protected $casts = [
        'installed_at' => 'date',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVersion()
    {
        return $this->belongsTo(ProductVersion::class);
    }

    public function getAccountUidAttribute(): ?string
    {
        return $this->account?->uid;
    }

    public function getProductUidAttribute(): ?string
    {
        return $this->product?->uid;
    }

    public function getProductVersionUidAttribute(): ?string
    {
        return $this->productVersion?->uid;
    }
}
