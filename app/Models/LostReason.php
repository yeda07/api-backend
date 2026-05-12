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
        'currency',
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
        'competitor_name',
        'opportunity_uid',
        'owner_user_uid',
        'entity_uid',
        'entity_type',
        'account_name',
        'deal_value',
        'lost_reason_category',
        'lost_reason_detail',
        'closed_date',
        'sales_rep',
    ];

    protected $casts = [
        'lost_at' => 'date',
        'estimated_value' => 'decimal:2',
        'currency' => 'string',
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

    public function getCompetitorNameAttribute(): ?string
    {
        return $this->competitor?->name;
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

    public function getAccountNameAttribute(): ?string
    {
        return $this->lossable?->display_name
            ?? $this->lossable?->name
            ?? $this->opportunity?->account?->name
            ?? null;
    }

    public function getDealValueAttribute(): float
    {
        return round((float) ($this->estimated_value ?? 0), 2);
    }

    public function getLostReasonCategoryAttribute(): string
    {
        return match ($this->reason_type) {
            'price' => 'Precio',
            'features' => 'Producto',
            'relationship' => 'Relacion',
            'timing' => 'Timing',
            'implementation', 'procurement' => 'Servicio',
            default => 'Otro',
        };
    }

    public function getLostReasonDetailAttribute(): ?string
    {
        return $this->details ?? $this->summary;
    }

    public function getClosedDateAttribute(): ?string
    {
        return $this->lost_at?->toISOString();
    }

    public function getSalesRepAttribute(): ?string
    {
        return $this->owner?->name;
    }
}
