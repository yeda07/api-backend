<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class OpportunityConflict extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'account_id',
        'partner_opportunity_id',
        'conflicting_partner_opportunity_id',
        'conflict_reason',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'account_id',
        'partner_opportunity_id',
        'conflicting_partner_opportunity_id',
    ];

    protected $appends = [
        'account_uid',
        'partner_opportunity_uid',
        'conflicting_partner_opportunity_uid',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function partnerOpportunity()
    {
        return $this->belongsTo(PartnerOpportunity::class);
    }

    public function conflictingPartnerOpportunity()
    {
        return $this->belongsTo(PartnerOpportunity::class, 'conflicting_partner_opportunity_id');
    }

    public function getAccountUidAttribute()
    {
        return $this->account?->uid;
    }

    public function getPartnerOpportunityUidAttribute()
    {
        return $this->partnerOpportunity?->uid;
    }

    public function getConflictingPartnerOpportunityUidAttribute()
    {
        return $this->conflictingPartnerOpportunity?->uid;
    }
}
