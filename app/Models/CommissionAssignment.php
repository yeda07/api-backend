<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionAssignment extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'user_id',
        'commission_plan_id',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'user_id',
        'commission_plan_id',
    ];

    protected $appends = [
        'user_uid',
        'user_name',
        'commission_plan_uid',
        'plan_uid',
        'plan_name',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function commissionPlan()
    {
        return $this->belongsTo(CommissionPlan::class);
    }

    public function getUserUidAttribute(): ?string
    {
        return $this->user?->uid
            ?? ($this->user_id ? User::query()->whereKey($this->user_id)->value('uid') : null);
    }

    public function getCommissionPlanUidAttribute(): ?string
    {
        return $this->commissionPlan?->uid
            ?? ($this->commission_plan_id ? CommissionPlan::query()->whereKey($this->commission_plan_id)->value('uid') : null);
    }

    public function getUserNameAttribute(): ?string
    {
        return $this->user?->name
            ?? ($this->user_id ? User::query()->whereKey($this->user_id)->value('name') : null);
    }

    public function getPlanUidAttribute(): ?string
    {
        return $this->commission_plan_uid;
    }

    public function getPlanNameAttribute(): ?string
    {
        return $this->commissionPlan?->name
            ?? ($this->commission_plan_id ? CommissionPlan::query()->whereKey($this->commission_plan_id)->value('name') : null);
    }

    public function getStartDateAttribute(): ?string
    {
        return $this->starts_at?->toDateString();
    }

    public function getEndDateAttribute(): ?string
    {
        return $this->ends_at?->toDateString();
    }

    public function getStatusAttribute(): string
    {
        return $this->active ? 'active' : 'inactive';
    }
}
