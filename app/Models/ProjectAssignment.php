<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class ProjectAssignment extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'project_id',
        'user_id',
        'role',
        'hours_allocated',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'project_id',
        'user_id',
    ];

    protected $appends = [
        'project_uid',
        'user_uid',
    ];

    protected $casts = [
        'hours_allocated' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getProjectUidAttribute()
    {
        return $this->project?->uid
            ?? ($this->project_id ? Project::query()->whereKey($this->project_id)->value('uid') : null);
    }

    public function getUserUidAttribute()
    {
        return $this->user?->uid
            ?? ($this->user_id ? User::query()->whereKey($this->user_id)->value('uid') : null);
    }
}
