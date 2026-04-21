<?php

namespace App\Repositories;

use App\Models\Product;

class ProductRepository
{
    public function query()
    {
        return Product::query()->with(['inventoryProduct', 'versions']);
    }

    public function all(array $filters = [])
    {
        $query = $this->query()->orderBy('name');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function findByUid(string $uid): Product
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): Product
    {
        return Product::query()->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh(['inventoryProduct', 'versions']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
