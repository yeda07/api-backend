<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope, AppliesRowLevelSecurity, HasUserTimezone;

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'assigned_user_id',
        'type',
        'title',
        'description',
        'status',
        'scheduled_at',
        'completed_at',
        'activityable_type',
        'activityable_id',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
        'assigned_user_id',
        'activityable_id',
    ];

    protected $appends = [
        'owner_user_uid',
        'assigned_user_uid',
        'activityable_uid',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function activityable()
    {
        return $this->morphTo();
    }

    public function getOwnerUserUidAttribute()
    {
        return $this->owner?->uid;
    }

    public function getAssignedUserUidAttribute()
    {
        return $this->assignedUser?->uid;
    }

    public function getActivityableUidAttribute()
    {
        return $this->activityable?->uid;
    }

    public function resolveDefaultOwnerUserId(): ?int
    {
        return auth()->id();
    }
}

