<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantSchemaService
{
    public function provision(Tenant $tenant): string
    {
        return $this->createSchema($tenant);
    }

    public function createSchema(Tenant $tenant): string
    {
        $schemaName = $this->ensureSchemaName($tenant);

        if ($this->supportsSchemas()) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS '.$this->quoteIdentifier($schemaName));
        }

        return $schemaName;
    }

    public function dropSchema(Tenant $tenant, bool $cascade = false): void
    {
        if (! $this->supportsSchemas() || ! $tenant->schema_name) {
            return;
        }

        $suffix = $cascade ? ' CASCADE' : ' RESTRICT';

        DB::statement('DROP SCHEMA IF EXISTS '.$this->quoteIdentifier($tenant->schema_name).$suffix);
    }

    public function runTenantMigrations(Tenant $tenant, string $path = 'database/migrations/tenant', bool $pretend = false): string
    {
        $this->createSchema($tenant);
        $this->setSearchPath($tenant);

        $originalMigrationTable = config('database.migrations.table', 'migrations');

        try {
            config(['database.migrations.table' => 'tenant_migrations']);

            Artisan::call('migrate', array_filter([
                '--path' => $path,
                '--force' => true,
                '--pretend' => $pretend ?: null,
            ], fn ($value) => $value !== null));

            return Artisan::output();
        } finally {
            config(['database.migrations.table' => $originalMigrationTable]);
            $this->resetSearchPath();
        }
    }

    public function generateSchemaName(Tenant $tenant): string
    {
        $prefix = Str::of((string) config('tenancy.schema_prefix', 'tenant'))
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value() ?: 'tenant';

        $uid = $tenant->uid ?: (string) Str::uuid();
        $suffix = Str::of($uid)
            ->lower()
            ->replace('-', '_')
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value();

        return Str::limit($prefix.'_'.$suffix, 63, '');
    }

    public function searchPath(Tenant $tenant): string
    {
        $schemaName = $tenant->schema_name ?: $this->generateSchemaName($tenant);

        return $this->quoteIdentifier($schemaName).', public';
    }

    public function setSearchPath(Tenant $tenant): void
    {
        if (! $this->supportsSchemas()) {
            return;
        }

        DB::statement('SET search_path TO '.$this->searchPath($tenant));
    }

    public function resetSearchPath(): void
    {
        if (! $this->supportsSchemas()) {
            return;
        }

        DB::statement('SET search_path TO public');
    }

    public function shouldUseSchemaMode(): bool
    {
        return config('tenancy.mode') === 'schema';
    }

    public function tenantTables(): array
    {
        return array_values(array_unique(config('tenancy.tenant_tables', [])));
    }

    public function globalTables(): array
    {
        return array_values(array_unique(config('tenancy.global_tables', [])));
    }

    public function copyTenantData(Tenant $tenant, array $tables = [], bool $execute = false, bool $truncate = false): array
    {
        if (! $this->supportsSchemas()) {
            throw ValidationException::withMessages([
                'database' => ['La migracion por schema solo esta soportada en PostgreSQL'],
            ]);
        }

        $schemaName = $this->createSchema($tenant);
        $tables = $tables ?: $this->tenantTables();
        $results = [];

        foreach ($tables as $table) {
            $table = trim((string) $table);

            if ($table === '') {
                continue;
            }

            $publicExists = Schema::hasTable($table);
            $hasTenantId = $publicExists && Schema::hasColumn($table, 'tenant_id');
            $tenantExists = $this->tableExists($schemaName, $table);
            $sourceCount = $hasTenantId
                ? (int) DB::table($table)->where('tenant_id', $tenant->getKey())->count()
                : null;

            $result = [
                'table' => $table,
                'public_exists' => $publicExists,
                'tenant_exists' => $tenantExists,
                'has_tenant_id' => $hasTenantId,
                'source_count' => $sourceCount,
                'copied' => 0,
                'status' => 'dry_run',
            ];

            if (! $publicExists) {
                $result['status'] = 'missing_public_table';
                $results[] = $result;
                continue;
            }

            if (! $hasTenantId) {
                $result['status'] = 'missing_tenant_id';
                $results[] = $result;
                continue;
            }

            if (! $tenantExists) {
                $result['status'] = 'missing_tenant_table';
                $results[] = $result;
                continue;
            }

            if ($execute) {
                if ($truncate) {
                    DB::statement('TRUNCATE TABLE '.$this->qualifiedTable($schemaName, $table).' RESTART IDENTITY CASCADE');
                }

                $result['copied'] = DB::affectingStatement(
                    'INSERT INTO '.$this->qualifiedTable($schemaName, $table)
                    .' SELECT * FROM public.'.$this->quoteIdentifier($table)
                    .' WHERE tenant_id = ? ON CONFLICT DO NOTHING',
                    [$tenant->getKey()]
                );
                $result['status'] = 'copied';
            }

            $results[] = $result;
        }

        return [
            'tenant_uid' => $tenant->uid,
            'schema_name' => $schemaName,
            'execute' => $execute,
            'truncate' => $truncate && $execute,
            'tables' => $results,
        ];
    }

    public function tableExists(string $schemaName, string $table): bool
    {
        if (! $this->supportsSchemas()) {
            return false;
        }

        return DB::table('information_schema.tables')
            ->where('table_schema', $schemaName)
            ->where('table_name', $table)
            ->exists();
    }

    private function supportsSchemas(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private function ensureSchemaName(Tenant $tenant): string
    {
        $schemaName = $tenant->schema_name ?: $this->generateSchemaName($tenant);

        if ($tenant->schema_name !== $schemaName) {
            $tenant->forceFill(['schema_name' => $schemaName])->save();
        }

        return $schemaName;
    }

    private function qualifiedTable(string $schemaName, string $table): string
    {
        return $this->quoteIdentifier($schemaName).'.'.$this->quoteIdentifier($table);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
