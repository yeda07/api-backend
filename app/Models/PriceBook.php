<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PriceBook extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'name',
        'key',
        'channel',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(PriceBookItem::class);
    }
}
