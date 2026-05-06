<?php

namespace App\Models;

use App\Models\Account;
use App\Models\Contact;
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
        'priority',
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
        'assigned_to_uid',
        'assigned_to_name',
        'activityable_uid',
        'contact_uid',
        'contact_name',
        'account_uid',
        'account_name',
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

    public function getAssignedToUidAttribute()
    {
        return $this->assignedUser?->uid;
    }

    public function getAssignedToNameAttribute()
    {
        return $this->assignedUser?->name;
    }

    public function getActivityableUidAttribute()
    {
        return $this->activityable?->uid;
    }

    public function getContactUidAttribute()
    {
        return $this->activityable instanceof Contact ? $this->activityable->uid : null;
    }

    public function getContactNameAttribute()
    {
        return $this->activityable instanceof Contact ? $this->activityable->display_name : null;
    }

    public function getAccountUidAttribute()
    {
        if ($this->activityable instanceof Account) {
            return $this->activityable->uid;
        }

        if ($this->activityable instanceof Contact) {
            return $this->activityable->account?->uid;
        }

        return null;
    }

    public function getAccountNameAttribute()
    {
        if ($this->activityable instanceof Account) {
            return $this->activityable->name;
        }

        if ($this->activityable instanceof Contact) {
            return $this->activityable->account?->name;
        }

        return null;
    }

    public function resolveDefaultOwnerUserId(): ?int
    {
        return auth()->id();
    }
}

