<?php

namespace App\Repositories;

use App\Models\AccountProduct;

class AccountProductRepository
{
    public function query()
    {
        return AccountProduct::query()->with(['account', 'product', 'productVersion']);
    }

    public function forAccount(string $accountUid)
    {
        return $this->query()
            ->whereHas('account', fn ($query) => $query->where('uid', $accountUid))
            ->latest('installed_at')
            ->get();
    }

    public function create(array $data): AccountProduct
    {
        return AccountProduct::query()->create($data)->fresh(['account', 'product', 'productVersion']);
    }
}
