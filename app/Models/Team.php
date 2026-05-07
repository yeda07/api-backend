<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'manager_user_id',
        'name',
        'description',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'manager_user_id',
    ];

    protected $appends = [
        'manager_uid',
        'manager_name',
        'leader_uid',
        'leader_name',
        'members_count',
        'members_contract',
        'member_uids',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function getManagerUidAttribute()
    {
        return $this->manager?->uid;
    }

    public function getManagerNameAttribute()
    {
        return $this->manager?->name;
    }

    public function getMemberUidsAttribute()
    {
        if (!$this->relationLoaded('members')) {
            return [];
        }

        return $this->members->pluck('uid')->values();
    }

    public function getLeaderUidAttribute()
    {
        return $this->manager_uid;
    }

    public function getLeaderNameAttribute()
    {
        return $this->manager_name;
    }

    public function getMembersCountAttribute(): int
    {
        return $this->relationLoaded('members')
            ? $this->members->count()
            : $this->members()->count();
    }

    public function getMembersContractAttribute()
    {
        if (!$this->relationLoaded('members')) {
            return [];
        }

        return $this->members->map(fn (User $user) => [
            'user_uid' => $user->uid,
            'user_name' => $user->name,
            'role_name' => $user->roles()->first()?->name,
            'assigned_clients' => Account::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant_id)
                ->where('owner_user_id', $user->getKey())
                ->count(),
        ])->values();
    }

    public function toArray()
    {
        $array = parent::toArray();

        if ($this->relationLoaded('members')) {
            $array['members'] = $this->members_contract;
        }

        unset($array['members_contract']);

        return $array;
    }
}
