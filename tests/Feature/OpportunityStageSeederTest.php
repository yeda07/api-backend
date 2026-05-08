<?php

namespace Tests\Feature;

use App\Models\OpportunityStage;
use App\Models\Tenant;
use Database\Seeders\OpportunityStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityStageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_base_stages_are_seeded_for_existing_tenants(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Pipeline',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->seed(OpportunityStageSeeder::class);

        $stages = OpportunityStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('position')
            ->pluck('name')
            ->all();

        $this->assertSame(['Leads', 'Contactado', 'Negociacion', 'Cerrador'], $stages);

        $this->seed(OpportunityStageSeeder::class);

        $this->assertSame(4, OpportunityStage::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count());
    }
}
