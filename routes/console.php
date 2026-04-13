<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\SearchBenchmarkService;
use Laravel\Sanctum\Sanctum;

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
