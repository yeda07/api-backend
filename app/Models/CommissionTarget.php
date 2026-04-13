<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionTarget extends Model
{
    use HasPublicUid, TenantScope;

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
}
