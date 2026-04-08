<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class FinancialRecord extends Model
{
    use HasPublicUid, TenantScope, AppliesRowLevelSecurity;

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'quotation_id',
        'record_type',
        'external_reference',
        'amount',
        'currency',
        'paid_at',
        'status',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
        'quotation_id',
    ];

    protected $appends = [
        'owner_user_uid',
        'quotation_uid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'meta' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function getOwnerUserUidAttribute()
    {
        return $this->owner?->uid
            ?? ($this->owner_user_id ? User::query()->whereKey($this->owner_user_id)->value('uid') : null);
    }

    public function getQuotationUidAttribute()
    {
        return $this->quotation?->uid
            ?? ($this->quotation_id ? Quotation::query()->whereKey($this->quotation_id)->value('uid') : null);
    }

    public function resolveDefaultOwnerUserId(): ?int
    {
        return auth()->id();
    }
}
