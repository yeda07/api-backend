<?php

namespace App\Repositories;

use App\Models\PartnerResource;

class PartnerResourceRepository
{
    public function query()
    {
        return PartnerResource::query()->with('partners')->latest();
    }

    public function all(array $filters = [])
    {
        return $this->query()
            ->when(!empty($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn ($query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOL)))
            ->when(!empty($filters['partner_uid']), function ($query) use ($filters) {
                $query->whereHas('partners', fn ($builder) => $builder->where('uid', $filters['partner_uid']));
            })
            ->get();
    }

    public function findByUid(string $uid): PartnerResource
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): PartnerResource
    {
        return PartnerResource::query()->create($data)->fresh('partners');
    }
}
