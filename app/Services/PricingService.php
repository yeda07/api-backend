<?php

namespace App\Services;

use App\Models\InventoryProduct;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use Illuminate\Validation\ValidationException;

class PricingService
{
    public function __construct(private readonly PriceBookService $priceBookService)
    {
    }

    public function resolveProductPrice(PriceBook $priceBook, InventoryProduct $product): PriceBookItem
    {
        $item = $this->priceBookService->getItemForProduct($priceBook, $product);

        if (!$item) {
            throw ValidationException::withMessages([
                'product_uid' => ['No existe precio definido para el producto en el canal seleccionado'],
            ]);
        }

        return $item;
    }
}
