<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'account_id',
        'opportunity_id',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'account_id',
        'opportunity_id',
    ];

    protected $appends = [
        'account_uid',
        'opportunity_uid',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('order');
    }

    public function assignments()
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function getAccountUidAttribute()
    {
        return $this->account?->uid
            ?? ($this->account_id ? Account::query()->whereKey($this->account_id)->value('uid') : null);
    }

    public function getOpportunityUidAttribute()
    {
        return $this->opportunity?->uid
            ?? ($this->opportunity_id ? Opportunity::query()->whereKey($this->opportunity_id)->value('uid') : null);
    }
}
