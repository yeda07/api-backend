<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'account_id',
        'opportunity_id',
        'assigned_user_id',
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'estimated_hours',
        'actual_hours',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'account_id',
        'opportunity_id',
        'assigned_user_id',
    ];

    protected $appends = [
        'account_uid',
        'client_uid',
        'client_name',
        'opportunity_uid',
        'priority',
        'manager_uid',
        'assigned_to_uid',
        'assigned_to_name',
        'estimated_hours',
        'actual_hours',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('order');
    }

    public function assignments()
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function getAccountUidAttribute()
    {
        return $this->account?->uid
            ?? ($this->account_id ? Account::query()->whereKey($this->account_id)->value('uid') : null);
    }

    public function getClientUidAttribute(): ?string
    {
        return $this->account_uid;
    }

    public function getClientNameAttribute(): ?string
    {
        return $this->account?->name
            ?? ($this->account_id ? Account::query()->whereKey($this->account_id)->value('name') : null);
    }

    public function getOpportunityUidAttribute()
    {
        return $this->opportunity?->uid
            ?? ($this->opportunity_id ? Opportunity::query()->whereKey($this->opportunity_id)->value('uid') : null);
    }

    public function getStatusAttribute($value): string
    {
        return match ($value) {
            'pending' => 'planning',
            'active' => 'in_progress',
            default => $value,
        };
    }

    public function getPriorityAttribute(): string
    {
        return $this->attributes['priority'] ?? 'medium';
    }

    public function getAssignedToUidAttribute(): ?string
    {
        return $this->assignedUser?->uid
            ?? ($this->assigned_user_id ? User::query()->whereKey($this->assigned_user_id)->value('uid') : null)
            ?? $this->assignments()->with('user')->first()?->user?->uid;
    }

    public function getManagerUidAttribute(): ?string
    {
        return $this->assigned_to_uid;
    }

    public function getAssignedToNameAttribute(): ?string
    {
        return $this->assignedUser?->name
            ?? ($this->assigned_user_id ? User::query()->whereKey($this->assigned_user_id)->value('name') : null)
            ?? $this->assignments()->with('user')->first()?->user?->name;
    }

    public function getEstimatedHoursAttribute(): float
    {
        return round((float) ($this->attributes['estimated_hours'] ?? $this->assignments()->sum('hours_allocated')), 2);
    }

    public function getActualHoursAttribute(): float
    {
        return round((float) ($this->attributes['actual_hours'] ?? 0), 2);
    }
}
