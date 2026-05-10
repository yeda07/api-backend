<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\OpportunityStageProvisioner;
use Illuminate\Database\Seeder;

class OpportunityStageSeeder extends Seeder
{
    public function run(OpportunityStageProvisioner $stageProvisioner): void
    {
        Tenant::query()->get()->each(function (Tenant $tenant) use ($stageProvisioner) {
            $stageProvisioner->provision($tenant);
        });
    }
}
