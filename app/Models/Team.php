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
}
