<?php

namespace App\Repositories;

use App\Models\Partner;
use App\Support\ApiIndex;

class PartnerRepository
{
    public function query()
    {
        return Partner::query()->with(['account', 'resources'])->orderBy('name');
    }

    public function all(array $filters = [])
    {
        return ApiIndex::paginateOrGet(
            $this->query()
                ->when(!empty($filters['type']), fn ($query) => $query->where('type', $filters['type']))
                ->when(!empty($filters['status']), fn ($query) => $query->where('status', $filters['status'])),
            $filters,
            'partners_page'
        );
    }

    public function findByUid(string $uid): Partner
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): Partner
    {
        return Partner::query()->create($data)->fresh(['account', 'resources']);
    }

    public function update(Partner $partner, array $data): Partner
    {
        $partner->update($data);

        return $partner->fresh(['account', 'resources']);
    }
}
