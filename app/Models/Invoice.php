<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'quotation_id',
        'invoiceable_type',
        'invoiceable_id',
        'invoice_number',
        'status',
        'quote_currency',
        'exchange_rate',
        'currency',
        'subtotal',
        'discount_total',
        'total',
        'paid_total',
        'outstanding_total',
        'issued_at',
        'due_date',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'quotation_id',
        'invoiceable_type',
        'invoiceable_id',
    ];

    protected $appends = [
        'quotation_uid',
        'invoiceable_uid',
        'entity_type',
        'entity_label',
        'entity_uid',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'outstanding_total' => 'decimal:2',
        'issued_at' => 'date',
        'due_date' => 'date',
        'meta' => 'array',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function invoiceable()
    {
        return $this->morphTo();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getQuotationUidAttribute()
    {
        return $this->quotation?->uid
            ?? ($this->quotation_id ? Quotation::query()->whereKey($this->quotation_id)->value('uid') : null);
    }

    public function getInvoiceableUidAttribute()
    {
        return $this->invoiceable?->uid;
    }

    public function getEntityTypeAttribute(): ?string
    {
        return $this->normalizeInvoiceableType($this->invoiceable_type);
    }

    public function getEntityLabelAttribute(): ?string
    {
        return match ($this->entity_type) {
            'account' => 'Cuenta',
            'contact' => 'Contacto',
            'crm_entity' => 'Entidad CRM',
            'opportunity' => 'Oportunidad',
            'tenant' => 'Tenant',
            null => null,
            default => Str::headline(str_replace(['_', '-'], ' ', $this->entity_type)),
        };
    }

    public function getEntityUidAttribute(): ?string
    {
        return $this->invoiceable_uid;
    }

    private function normalizeInvoiceableType(?string $type): ?string
    {
        return match ($type) {
            Account::class => 'account',
            Contact::class => 'contact',
            CrmEntity::class => 'crm_entity',
            Opportunity::class => 'opportunity',
            Tenant::class => 'tenant',
            null, '' => null,
            default => Str::of(class_basename($type))->snake()->toString(),
        };
    }
}
