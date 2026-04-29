<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformSuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL');

        if (!$email) {
            return;
        }

        $name = env('SUPERADMIN_NAME', 'Platform Superadmin');
        $password = env('SUPERADMIN_PASSWORD', 'secret123');

        $user = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'tenant_id' => null,
                'is_platform_admin' => true,
            ]
        );

        $permission = Permission::query()->where('key', 'plans.manage')->first();

        if ($permission) {
            $user->givePermissionTo($permission);
        }
    }
}
