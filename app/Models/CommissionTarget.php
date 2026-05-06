<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionTarget extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'user_id',
        'period',
        'target_amount',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'user_id',
    ];

    protected $appends = [
        'user_uid',
        'user_name',
        'metric',
        'goal_value',
        'current_value',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUserUidAttribute(): ?string
    {
        return $this->user?->uid
            ?? ($this->user_id ? User::query()->whereKey($this->user_id)->value('uid') : null);
    }

    public function getUserNameAttribute(): ?string
    {
        return $this->user?->name
            ?? ($this->user_id ? User::query()->whereKey($this->user_id)->value('name') : null);
    }

    public function getMetricAttribute(): string
    {
        return 'total_sales';
    }

    public function getGoalValueAttribute(): float
    {
        return round((float) $this->target_amount, 2);
    }

    public function getCurrentValueAttribute(): float
    {
        return 0.0;
    }
}
