<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'invoice_id',
        'amount',
        'payment_date',
        'method',
        'external_reference',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'invoice_id',
    ];

    protected $appends = [
        'invoice_uid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'meta' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getInvoiceUidAttribute()
    {
        return $this->invoice?->uid
            ?? ($this->invoice_id ? Invoice::query()->whereKey($this->invoice_id)->value('uid') : null);
    }
}
