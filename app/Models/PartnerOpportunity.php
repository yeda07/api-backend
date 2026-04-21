<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PartnerOpportunity extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'partner_id',
        'account_id',
        'opportunity_id',
        'title',
        'status',
        'conflict_scope',
        'amount',
        'currency',
        'description',
        'closed_at',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'partner_id',
        'account_id',
        'opportunity_id',
    ];

    protected $appends = [
        'partner_uid',
        'account_uid',
        'opportunity_uid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function conflicts()
    {
        return $this->hasMany(OpportunityConflict::class);
    }

    public function getPartnerUidAttribute()
    {
        return $this->partner?->uid;
    }

    public function getAccountUidAttribute()
    {
        return $this->account?->uid;
    }

    public function getOpportunityUidAttribute()
    {
        return $this->opportunity?->uid;
    }
}
