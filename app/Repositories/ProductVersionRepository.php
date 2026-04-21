<?php

namespace App\Repositories;

use App\Models\ProductVersion;

class ProductVersionRepository
{
    public function query()
    {
        return ProductVersion::query()->with('product');
    }

    public function findByUid(string $uid): ProductVersion
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): ProductVersion
    {
        return ProductVersion::query()->create($data)->fresh('product');
    }

    public function update(ProductVersion $version, array $data): ProductVersion
    {
        $version->update($data);

        return $version->fresh('product');
    }
}
