<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'account_id',
        'name',
        'type',
        'status',
        'contact_info',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'account_id',
    ];

    protected $appends = [
        'account_uid',
        'partner_type',
        'contact_name',
        'contact_email',
        'phone',
        'region',
        'registered_opportunities',
        'converted_deals',
        'joined_date',
        'notes',
    ];

    protected $casts = [
        'contact_info' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function opportunities()
    {
        return $this->hasMany(PartnerOpportunity::class);
    }

    public function resources()
    {
        return $this->belongsToMany(PartnerResource::class, 'partner_access')->withTimestamps();
    }

    public function getAccountUidAttribute()
    {
        return $this->account?->uid;
    }

    public function getPartnerTypeAttribute(): ?string
    {
        return $this->type;
    }

    public function getContactNameAttribute(): ?string
    {
        return $this->contact_info['contact_name'] ?? $this->contact_info['name'] ?? null;
    }

    public function getContactEmailAttribute(): ?string
    {
        return $this->contact_info['contact_email'] ?? $this->contact_info['email'] ?? null;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->contact_info['phone'] ?? null;
    }

    public function getRegionAttribute(): ?string
    {
        return $this->contact_info['region'] ?? null;
    }

    public function getRegisteredOpportunitiesAttribute(): int
    {
        return $this->relationLoaded('opportunities')
            ? $this->opportunities->count()
            : $this->opportunities()->count();
    }

    public function getConvertedDealsAttribute(): int
    {
        return $this->relationLoaded('opportunities')
            ? $this->opportunities->whereIn('status', ['won', 'closed'])->count()
            : $this->opportunities()->whereIn('status', ['won', 'closed'])->count();
    }

    public function getJoinedDateAttribute(): ?string
    {
        return $this->created_at?->toISOString();
    }

    public function getNotesAttribute(): ?string
    {
        return $this->contact_info['notes'] ?? null;
    }
}
