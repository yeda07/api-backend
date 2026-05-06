<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\AdminAlertEvaluatorService;
use App\Services\SearchBenchmarkService;
use App\Models\Permission;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('search:benchmark {--tenant_uid=} {--user_uid=} {--iterations=5}', function () {
    $tenantUid = $this->option('tenant_uid');
    $userUid = $this->option('user_uid');
    $iterations = (int) $this->option('iterations');

    $tenant = $tenantUid
        ? Tenant::query()->where('uid', $tenantUid)->first()
        : Tenant::query()->first();

    if (!$tenant) {
        $this->error('No hay tenants disponibles para ejecutar el benchmark.');
        return 1;
    }

    $user = $userUid
        ? User::query()->where('uid', $userUid)->first()
        : User::query()->where('tenant_id', $tenant->getKey())->first();

    if (!$user) {
        $this->error('No hay usuarios disponibles para ejecutar el benchmark.');
        return 1;
    }

    Sanctum::actingAs($user, [
        'access:full',
        'tenant:' . $tenant->uid,
    ]);

    $results = app(SearchBenchmarkService::class)->run($iterations);

    foreach ($results as $name => $result) {
        $this->info("Scenario: {$name}");
        $this->line('  avg_ms: ' . $result['avg_ms']);
        $this->line('  min_ms: ' . $result['min_ms']);
        $this->line('  max_ms: ' . $result['max_ms']);
        $this->line('  iterations: ' . $result['iterations']);
    }

    return 0;
})->purpose('Measure search performance using representative backend scenarios');

Artisan::command('finance:sync-overdue', function () {
    $result = app(InvoiceService::class)->syncOverdue();

    $this->info('Facturas vencidas sincronizadas: ' . $result['updated_invoices']);

    return 0;
})->purpose('Mark issued and partial invoices as overdue when due date has passed');

Artisan::command('admin-alerts:evaluate', function () {
    $result = app(AdminAlertEvaluatorService::class)->evaluateActiveRules();

    $this->info('Alertas evaluadas: ' . $result['evaluated']);
    $this->info('Alertas disparadas: ' . $result['triggered']);

    return 0;
})->purpose('Evaluate active platform telemetry alert rules');

Artisan::command('superadmin:create {email} {--name=Platform Superadmin} {--password=} {--regenerate-password}', function (string $email) {
    $name = (string) $this->option('name');
    $password = $this->option('password');
    $regeneratePassword = (bool) $this->option('regenerate-password');
    $shouldShowPassword = false;

    if (!$password) {
        $password = \Illuminate\Support\Str::random(16);
    }

    $user = User::withoutGlobalScopes()->where('email', $email)->first();

    if ($user && !$regeneratePassword) {
        $this->warn('El usuario ya existe. Se mantuvo su password actual.');
    }

    if (!$user || $regeneratePassword || $this->option('password')) {
        $shouldShowPassword = true;
    }

    $user = User::withoutGlobalScopes()->updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => $user && !$regeneratePassword ? $user->password : Hash::make($password),
            'tenant_id' => null,
            'is_platform_admin' => true,
        ]
    );

    $permission = Permission::query()->where('key', 'plans.manage')->first();

    if (!$permission) {
        $this->error('No existe el permiso plans.manage. Ejecuta primero los seeders de permisos.');
        return 1;
    }

    $user->givePermissionTo($permission);

    $this->info('Superadmin global listo.');
    $this->line('Email: ' . $user->email);
    $this->line('Nombre: ' . $user->name);
    $this->line('UID: ' . $user->uid);
    $this->line('is_platform_admin: true');
    $this->line('tenant_id: null');

    if ($shouldShowPassword) {
        $this->line('Password temporal: ' . $password);
    }

    return 0;
})->purpose('Create or update the first global platform superadmin user');
