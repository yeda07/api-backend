<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class AutomationAssignmentRule extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'assigned_to_user_id',
        'name',
        'description',
        'conditions',
        'logic',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'assigned_to_user_id',
    ];

    protected $appends = [
        'assigned_to_uid',
        'assigned_to_name',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
    ];

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function getAssignedToUidAttribute()
    {
        return $this->assignedTo?->uid;
    }

    public function getAssignedToNameAttribute()
    {
        return $this->assignedTo?->name;
    }
}
