<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope, AppliesRowLevelSecurity, HasUserTimezone;

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'assigned_user_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'taskable_type',
        'taskable_id',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
        'assigned_user_id',
        'taskable_id',
    ];

    protected $appends = [
        'owner_user_uid',
        'assigned_user_uid',
        'taskable_uid',
    ];

    protected $casts = [
        'due_date' => 'date',
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

    public function taskable()
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

    public function getTaskableUidAttribute()
    {
        return $this->taskable?->uid;
    }

    public function resolveDefaultOwnerUserId(): ?int
    {
        return auth()->id();
    }
}
