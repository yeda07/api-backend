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
        'assigned_user_ids',
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
        'user_ids',
        'user_names',
    ];

    protected $casts = [
        'assigned_user_ids' => 'array',
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

    public function getUserIdsAttribute(): array
    {
        $ids = $this->assigned_user_ids ?: array_filter([$this->assigned_to_user_id]);

        return User::query()
            ->whereIn('id', $ids)
            ->pluck('uid')
            ->values()
            ->all();
    }

    public function getUserNamesAttribute(): array
    {
        $ids = $this->assigned_user_ids ?: array_filter([$this->assigned_to_user_id]);

        return User::query()
            ->whereIn('id', $ids)
            ->pluck('name')
            ->values()
            ->all();
    }
}
