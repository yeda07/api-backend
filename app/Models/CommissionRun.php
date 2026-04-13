<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionRun extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'user_id',
        'commission_plan_id',
        'period_start',
        'period_end',
        'sales_amount',
        'margin_amount',
        'commission_amount',
        'status',
        'approved_at',
        'paid_at',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'user_id',
        'commission_plan_id',
    ];

    protected $appends = [
        'user_uid',
        'commission_plan_uid',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'sales_amount' => 'decimal:2',
        'margin_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'date',
        'paid_at' => 'date',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function commissionPlan()
    {
        return $this->belongsTo(CommissionPlan::class);
    }

    public function items()
    {
        return $this->hasMany(CommissionRunItem::class);
    }

    public function entries()
    {
        return $this->hasMany(CommissionEntry::class);
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
}
