<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class ProjectMilestone extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'project_id',
        'assigned_user_id',
        'name',
        'description',
        'due_date',
        'status',
        'order',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'project_id',
        'assigned_user_id',
    ];

    protected $appends = [
        'project_uid',
        'title',
        'assigned_to_uid',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getProjectUidAttribute()
    {
        return $this->project?->uid
            ?? ($this->project_id ? Project::query()->whereKey($this->project_id)->value('uid') : null);
    }

    public function getTitleAttribute(): ?string
    {
        return $this->name;
    }

    public function getStatusAttribute($value): string
    {
        return $value === 'done' ? 'completed' : $value;
    }

    public function getAssignedToUidAttribute(): ?string
    {
        return $this->assignedUser?->uid
            ?? ($this->assigned_user_id ? User::query()->whereKey($this->assigned_user_id)->value('uid') : null);
    }
}
