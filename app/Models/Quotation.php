<?php

namespace App\Models;

use App\Models\Traits\AppliesRowLevelSecurity;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope, AppliesRowLevelSecurity;

    protected $fillable = [
        'uid',
        'tenant_id',
        'owner_user_id',
        'created_by_user_id',
        'price_book_id',
        'quoteable_type',
        'quoteable_id',
        'quote_number',
        'title',
        'status',
        'currency',
        'exchange_rate',
        'local_currency',
        'valid_until',
        'notes',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'owner_user_id',
        'created_by_user_id',
        'price_book_id',
        'quoteable_id',
    ];

    protected $appends = [
        'owner_user_uid',
        'created_by_user_uid',
        'price_book_uid',
        'quoteable_uid',
        'subtotal',
        'discount_total',
        'total',
        'reserved_items_count',
        'reservation_indicator',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'exchange_rate' => 'decimal:6',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function quoteable()
    {
        return $this->morphTo();
    }

    public function priceBook()
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function getOwnerUserUidAttribute()
    {
        return $this->owner?->uid
            ?? ($this->owner_user_id ? User::query()->whereKey($this->owner_user_id)->value('uid') : null);
    }

    public function getCreatedByUserUidAttribute()
    {
        return $this->createdBy?->uid
            ?? ($this->created_by_user_id ? User::query()->whereKey($this->created_by_user_id)->value('uid') : null);
    }

    public function getQuoteableUidAttribute()
    {
        return $this->quoteable?->uid;
    }

    public function getPriceBookUidAttribute()
    {
        return $this->priceBook?->uid
            ?? ($this->price_book_id ? PriceBook::query()->whereKey($this->price_book_id)->value('uid') : null);
    }

    public function getSubtotalAttribute(): float
    {
        return round((float) $this->items()->selectRaw('COALESCE(SUM(quantity * list_unit_price), 0) as subtotal')->value('subtotal'), 2);
    }

    public function getDiscountTotalAttribute(): float
    {
        return round((float) $this->items()->selectRaw('COALESCE(SUM(quantity * discount_amount), 0) as total_discount')->value('total_discount'), 2);
    }

    public function getTotalAttribute(): float
    {
        return round((float) $this->items()->selectRaw('COALESCE(SUM(quantity * net_unit_price), 0) as total')->value('total'), 2);
    }

    public function getReservedItemsCountAttribute(): int
    {
        return $this->items()->get()->filter(fn (QuotationItem $item) => $item->reserved_quantity > 0)->count();
    }

    public function getReservationIndicatorAttribute(): string
    {
        $items = $this->items()->get();

        if ($items->isEmpty()) {
            return 'not_reserved';
        }

        $fullyReserved = $items->every(fn (QuotationItem $item) => $item->reserved_quantity >= $item->quantity);
        $partiallyReserved = $items->contains(fn (QuotationItem $item) => $item->reserved_quantity > 0);

        return match (true) {
            $fullyReserved => 'reserved',
            $partiallyReserved => 'partial',
            default => 'not_reserved',
        };
    }

    public function resolveDefaultOwnerUserId(): ?int
    {
        return auth()->id();
    }
}
