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
        'assigned_to_user_id',
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
        'partner_name',
        'account_uid',
        'client_name',
        'client_email',
        'opportunity_uid',
        'product',
        'estimated_value',
        'registered_date',
        'notes',
        'assigned_to_internal',
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

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function conflicts()
    {
        return $this->hasMany(OpportunityConflict::class);
    }

    public function getStatusAttribute($value): string
    {
        return $value === 'open' ? 'pending' : $value;
    }

    public function getPartnerUidAttribute()
    {
        return $this->partner?->uid;
    }

    public function getPartnerNameAttribute(): ?string
    {
        return $this->partner?->name;
    }

    public function getAccountUidAttribute()
    {
        return $this->account?->uid;
    }

    public function getClientNameAttribute(): ?string
    {
        return $this->account?->name;
    }

    public function getClientEmailAttribute(): ?string
    {
        return $this->account?->email;
    }

    public function getOpportunityUidAttribute()
    {
        return $this->opportunity?->uid;
    }

    public function getProductAttribute(): ?string
    {
        return $this->description ? data_get(json_decode($this->description, true), 'product') : null;
    }

    public function getEstimatedValueAttribute(): float
    {
        return round((float) $this->amount, 2);
    }

    public function getRegisteredDateAttribute(): ?string
    {
        return $this->created_at?->toISOString();
    }

    public function getNotesAttribute(): ?string
    {
        $decoded = $this->description ? json_decode($this->description, true) : null;

        return is_array($decoded) ? ($decoded['notes'] ?? null) : $this->description;
    }

    public function getAssignedToInternalAttribute(): ?string
    {
        return $this->assignedToUser?->name;
    }
}
