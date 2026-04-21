<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionRunItem extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'commission_run_id',
        'commission_entry_id',
        'source_type',
        'source_uid',
        'base_amount',
        'applied_percent',
        'commission_amount',
        'rule_snapshot_json',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'commission_run_id',
        'commission_entry_id',
    ];

    protected $appends = [
        'commission_run_uid',
        'commission_entry_uid',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'applied_percent' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'rule_snapshot_json' => 'array',
    ];

    public function commissionRun()
    {
        return $this->belongsTo(CommissionRun::class);
    }

    public function commissionEntry()
    {
        return $this->belongsTo(CommissionEntry::class);
    }

    public function getCommissionRunUidAttribute(): ?string
    {
        return $this->commissionRun?->uid
            ?? ($this->commission_run_id ? CommissionRun::query()->whereKey($this->commission_run_id)->value('uid') : null);
    }

    public function getCommissionEntryUidAttribute(): ?string
    {
        return $this->commissionEntry?->uid
            ?? ($this->commission_entry_id ? CommissionEntry::query()->whereKey($this->commission_entry_id)->value('uid') : null);
    }
}
