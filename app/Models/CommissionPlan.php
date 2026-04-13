<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionPlan extends Model
{
    use HasPublicUid, TenantScope;

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
}
