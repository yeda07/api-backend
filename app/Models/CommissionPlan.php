<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionPlan extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'name',
        'type',
        'base_percent',
        'tiers_json',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    protected $appends = [
        'role_uids',
        'base_percentage',
        'tiers',
        'is_active',
    ];

    protected $casts = [
        'base_percent' => 'decimal:2',
        'tiers_json' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'active' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'commission_plan_role')
            ->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(CommissionAssignment::class);
    }

    public function getRoleUidsAttribute(): array
    {
        return $this->relationLoaded('roles')
            ? $this->roles->pluck('uid')->values()->all()
            : $this->roles()->pluck('roles.uid')->values()->all();
    }

    public function getBasePercentageAttribute(): float
    {
        return round((float) $this->base_percent, 2);
    }

    public function getTiersAttribute(): array
    {
        return collect($this->tiers_json ?? [])
            ->map(fn (array $tier) => [
                'threshold' => (float) ($tier['threshold'] ?? 0),
                'percentage' => (float) ($tier['percentage'] ?? $tier['percent'] ?? 0),
                'percent' => (float) ($tier['percent'] ?? $tier['percentage'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function getIsActiveAttribute(): bool
    {
        return (bool) $this->active;
    }
}
