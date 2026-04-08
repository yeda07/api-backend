<?php
namespace App\Services;

use App\Repositories\CrmEntityRepository;
use App\Factories\CrmEntityFactory;
use App\Services\PlanLimitService; // 🔥 IMPORTANTE

class CrmEntityService
{
    protected $repo;

    public function __construct(CrmEntityRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getAll()
    {
        return $this->repo->all();
    }

    public function create(array $data)
    {
        // 🔥 VALIDACIÓN DE PLAN DINÁMICA POR TIPO
        PlanLimitService::check($data['type']);

        $validatedProfile = CrmEntityFactory::make(
            $data['type'],
            $data['profile_data']
        );

        return $this->repo->create([
            'tenant_id'    => auth()->user()->tenant_id,
            'type'         => $data['type'],
            'profile_data' => $validatedProfile
        ]);
    }
}
