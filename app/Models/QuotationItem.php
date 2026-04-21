<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'quotation_id',
        'product_id',
        'catalog_product_id',
        'warehouse_id',
        'sku',
        'description',
        'quantity',
        'list_unit_price',
        'discount_percent',
        'discount_amount',
        'net_unit_price',
        'unit_cost',
        'unit_price',
        'margin_amount',
        'margin_percent',
        'min_margin_percent',
        'below_min_margin',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'quotation_id',
        'product_id',
        'catalog_product_id',
        'warehouse_id',
    ];

    protected $appends = [
        'quotation_uid',
        'product_uid',
        'catalog_product_uid',
        'warehouse_uid',
        'line_total',
        'list_line_total',
        'discount_total',
        'reserved_quantity',
        'reservation_indicator',
        'stock_snapshot',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'list_unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'margin_amount' => 'decimal:2',
        'margin_percent' => 'decimal:2',
        'min_margin_percent' => 'decimal:2',
        'below_min_margin' => 'boolean',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function catalogProduct()
    {
        return $this->belongsTo(Product::class, 'catalog_product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getQuotationUidAttribute()
    {
        return $this->quotation?->uid
            ?? ($this->quotation_id ? Quotation::query()->whereKey($this->quotation_id)->value('uid') : null);
    }

    public function getProductUidAttribute()
    {
        return $this->product?->uid
            ?? ($this->product_id ? InventoryProduct::query()->whereKey($this->product_id)->value('uid') : null);
    }

    public function getCatalogProductUidAttribute()
    {
        return $this->catalogProduct?->uid
            ?? ($this->catalog_product_id ? Product::query()->whereKey($this->catalog_product_id)->value('uid') : null);
    }

    public function getWarehouseUidAttribute()
    {
        return $this->warehouse?->uid
            ?? ($this->warehouse_id ? Warehouse::query()->whereKey($this->warehouse_id)->value('uid') : null);
    }

    public function getLineTotalAttribute(): float
    {
        return round(((float) $this->net_unit_price) * $this->quantity, 2);
    }

    public function getListLineTotalAttribute(): float
    {
        return round(((float) $this->list_unit_price) * $this->quantity, 2);
    }

    public function getDiscountTotalAttribute(): float
    {
        return round(((float) $this->discount_amount) * $this->quantity, 2);
    }

    public function getReservedQuantityAttribute(): int
    {
        return (int) InventoryReservation::query()
            ->where('source_type', 'quotation_item')
            ->where('source_uid', $this->uid)
            ->where('status', 'active')
            ->sum('quantity');
    }

    public function getReservationIndicatorAttribute(): string
    {
        return match (true) {
            $this->reserved_quantity >= $this->quantity && $this->quantity > 0 => 'reserved',
            $this->reserved_quantity > 0 => 'partial',
            default => 'not_reserved',
        };
    }

    public function getStockSnapshotAttribute(): array
    {
        if (!$this->product_id || !$this->warehouse_id) {
            return [
                'stock_actual' => null,
                'stock_reservado_actual' => null,
                'stock_disponible' => null,
                'resultado_final_proyectado' => null,
            ];
        }

        $stock = InventoryStock::query()
            ->where('product_id', $this->product_id)
            ->where('warehouse_id', $this->warehouse_id)
            ->first();

        $physical = (int) ($stock?->physical_stock ?? 0);
        $reserved = (int) ($stock?->reserved_stock ?? 0);
        $available = max(0, $physical - $reserved);

        return [
            'stock_actual' => $physical,
            'stock_reservado_actual' => $reserved,
            'stock_disponible' => $available,
            'resultado_final_proyectado' => $available - max(0, $this->quantity - $this->reserved_quantity),
        ];
    }
}
