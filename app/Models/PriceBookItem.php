<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PriceBookItem extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'price_book_id',
        'product_id',
        'unit_price',
        'currency',
        'min_margin_percent',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'price_book_id',
        'product_id',
    ];

    protected $appends = [
        'price_book_uid',
        'product_uid',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'min_margin_percent' => 'decimal:2',
    ];

    public function priceBook()
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function getPriceBookUidAttribute()
    {
        return $this->priceBook?->uid
            ?? ($this->price_book_id ? PriceBook::query()->whereKey($this->price_book_id)->value('uid') : null);
    }

    public function getProductUidAttribute()
    {
        return $this->product?->uid
            ?? ($this->product_id ? InventoryProduct::query()->whereKey($this->product_id)->value('uid') : null);
    }
}
