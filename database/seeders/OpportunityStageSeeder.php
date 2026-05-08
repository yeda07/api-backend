<?php

namespace Database\Seeders;

use App\Models\OpportunityStage;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class OpportunityStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => 'Leads', 'key' => 'leads', 'position' => 1, 'probability_percent' => 10],
            ['name' => 'Contactado', 'key' => 'contactado', 'position' => 2, 'probability_percent' => 30],
            ['name' => 'Negociacion', 'key' => 'negociacion', 'position' => 3, 'probability_percent' => 70],
            ['name' => 'Cerrador', 'key' => 'cerrador', 'position' => 4, 'probability_percent' => 90],
        ];

        Tenant::query()->get()->each(function (Tenant $tenant) use ($stages) {
            foreach ($stages as $stage) {
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
        });
    }
}
