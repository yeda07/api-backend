<?php
namespace App\Repositories;

use App\Models\CrmEntity;

class CrmEntityRepository
{
    public function all()
    {
        return CrmEntity::all();
    }

    public function create(array $data)
    {
        return CrmEntity::create($data);
    }
}
