<?php

namespace App\Repositories;

use App\Models\PartnerResource;
use App\Support\ApiIndex;

class PartnerResourceRepository
{
    public function query()
    {
        return PartnerResource::query()->with('partners')->latest();
    }

    public function all(array $filters = [])
    {
        return ApiIndex::paginateOrGet(
            $this->query()
                ->when(!empty($filters['type']), fn ($query) => $query->where('type', $filters['type']))
                ->when(!empty($filters['search']), function ($query) use ($filters) {
                    $search = '%' . mb_strtolower($filters['search']) . '%';
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery->whereRaw('LOWER(title) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(original_name) LIKE ?', [$search]);
                    });
                })
                ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn ($query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOL)))
                ->when(!empty($filters['partner_uid']), function ($query) use ($filters) {
                    $query->whereHas('partners', fn ($builder) => $builder->where('uid', $filters['partner_uid']));
                }),
            $filters,
            'partner_resources_page'
        );
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
