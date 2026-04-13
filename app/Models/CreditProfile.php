<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CreditProfile extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'creditable_type',
        'creditable_id',
        'credit_limit',
        'max_days_overdue',
        'auto_block',
        'status',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'creditable_id',
    ];

    protected $appends = [
        'creditable_uid',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'max_days_overdue' => 'integer',
        'auto_block' => 'boolean',
        'meta' => 'array',
    ];

    public function creditable()
    {
        return $this->morphTo();
    }

    public function getCreditableUidAttribute()
    {
        return $this->creditable?->uid;
    }
}
