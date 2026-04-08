<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class CommissionEntry extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'user_id',
        'rule_id',
        'quotation_id',
        'quotation_item_id',
        'financial_record_id',
        'customer_type',
        'base_amount',
        'rate_percent',
        'commission_amount',
        'status',
        'earned_at',
        'paid_at',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'user_id',
        'rule_id',
        'quotation_id',
        'quotation_item_id',
        'financial_record_id',
    ];

    protected $appends = [
        'user_uid',
        'rule_uid',
        'quotation_uid',
        'quotation_item_uid',
        'financial_record_uid',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate_percent' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'earned_at' => 'date',
        'paid_at' => 'date',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rule()
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function quotationItem()
    {
        return $this->belongsTo(QuotationItem::class);
    }

    public function financialRecord()
    {
        return $this->belongsTo(FinancialRecord::class);
    }

    public function getUserUidAttribute()
    {
        return $this->user?->uid
            ?? User::query()->whereKey($this->user_id)->value('uid');
    }

    public function getRuleUidAttribute()
    {
        return $this->rule?->uid
            ?? ($this->rule_id ? CommissionRule::query()->whereKey($this->rule_id)->value('uid') : null);
    }

    public function getQuotationUidAttribute()
    {
        return $this->quotation?->uid
            ?? ($this->quotation_id ? Quotation::query()->whereKey($this->quotation_id)->value('uid') : null);
    }

    public function getQuotationItemUidAttribute()
    {
        return $this->quotationItem?->uid
            ?? ($this->quotation_item_id ? QuotationItem::query()->whereKey($this->quotation_item_id)->value('uid') : null);
    }

    public function getFinancialRecordUidAttribute()
    {
        return $this->financialRecord?->uid
            ?? ($this->financial_record_id ? FinancialRecord::query()->whereKey($this->financial_record_id)->value('uid') : null);
    }
}
