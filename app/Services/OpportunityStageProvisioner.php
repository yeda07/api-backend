<?php

namespace App\Services;

use App\Models\OpportunityStage;
use App\Models\Tenant;

class OpportunityStageProvisioner
{
    public function provision(Tenant $tenant): void
    {
        foreach ($this->baseStages() as $stage) {
            OpportunityStage::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'key' => $stage['key'],
                ],
                [
                    'name' => $stage['name'],
                    'position' => $stage['position'],
                    'probability_percent' => $stage['probability_percent'],
                    'is_won' => false,
                    'is_lost' => false,
                    'is_active' => true,
                ]
            );
        }
    }

    private function baseStages(): array
    {
        return [
            ['name' => 'Leads', 'key' => 'leads', 'position' => 1, 'probability_percent' => 10],
            ['name' => 'Contactado', 'key' => 'contactado', 'position' => 2, 'probability_percent' => 30],
            ['name' => 'Negociación', 'key' => 'negociacion', 'position' => 3, 'probability_percent' => 70],
            ['name' => 'Cerrador', 'key' => 'cerrador', 'position' => 4, 'probability_percent' => 90],
        ];
    }
}
