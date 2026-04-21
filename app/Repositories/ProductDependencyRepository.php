<?php

namespace App\Repositories;

use App\Models\ProductDependency;

class ProductDependencyRepository
{
    public function query()
    {
        return ProductDependency::query()->with(['product', 'dependsOnProduct']);
    }

    public function findByUid(string $uid): ProductDependency
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function forProduct(string $productUid)
    {
        return $this->query()->whereHas('product', fn ($query) => $query->where('uid', $productUid))->get();
    }

    public function create(array $data): ProductDependency
    {
        return ProductDependency::query()->create($data)->fresh(['product', 'dependsOnProduct']);
    }

    public function delete(ProductDependency $dependency): void
    {
        $dependency->delete();
    }
}
