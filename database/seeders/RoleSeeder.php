<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\TenantRoleProvisioner;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(TenantRoleProvisioner $roleProvisioner): void
    {
        foreach (Tenant::query()->get() as $tenant) {
            $roleProvisioner->provision($tenant);
        }
    }
}
