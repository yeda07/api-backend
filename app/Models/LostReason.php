<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class LostReason extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'competitor_id',
        'opportunity_id',
        'owner_user_id',
        'lossable_type',
        'lossable_id',
        'reason_type',
        'summary',
        'details',
        'lost_at',
        'estimated_value',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'competitor_id',
        'opportunity_id',
        'owner_user_id',
        'lossable_type',
        'lossable_id',
    ];

    protected $appends = [
        'competitor_uid',
        'opportunity_uid',
        'owner_user_uid',
        'entity_uid',
        'entity_type',
    ];

    protected $casts = [
        'lost_at' => 'date',
        'estimated_value' => 'decimal:2',
        'meta' => 'array',
    ];

    public function competitor()
    {
        return $this->belongsTo(Competitor::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function lossable()
    {
        return $this->morphTo();
    }

    public function getCompetitorUidAttribute(): ?string
    {
        return $this->competitor?->uid;
    }

    public function getOpportunityUidAttribute(): ?string
    {
        return $this->opportunity?->uid;
    }

    public function getOwnerUserUidAttribute(): ?string
    {
        return $this->owner?->uid;
    }

    public function getEntityUidAttribute(): ?string
    {
        return $this->lossable?->uid;
    }

    public function getEntityTypeAttribute(): ?string
    {
        return match ($this->lossable_type) {
            Account::class => 'account',
            Contact::class => 'contact',
            CrmEntity::class => 'crm-entity',
            default => null,
        };
    }
}
