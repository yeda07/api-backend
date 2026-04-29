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
    ];

    protected $appends = [
        'project_uid',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function getProjectUidAttribute()
    {
        return $this->project?->uid
            ?? ($this->project_id ? Project::query()->whereKey($this->project_id)->value('uid') : null);
    }
}
