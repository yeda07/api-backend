<?php
namespace App\Repositories;

use App\Models\CrmEntity;
use App\Support\ApiIndex;

class CrmEntityRepository
{
    public function all(array $filters = [])
    {
        return ApiIndex::paginateOrGet(
            CrmEntity::query()->latest(),
            $filters,
            'crm_entities_page'
        );
    }

    public function create(array $data)
    {
        return CrmEntity::create($data);
    }
}
