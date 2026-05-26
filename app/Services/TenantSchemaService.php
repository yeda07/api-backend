<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSchemaService
{
    public function provision(Tenant $tenant): string
    {
        $schemaName = $tenant->schema_name ?: $this->generateSchemaName($tenant);

        if ($tenant->schema_name !== $schemaName) {
            $tenant->forceFill(['schema_name' => $schemaName])->save();
        }

        if ($this->supportsSchemas()) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS '.$this->quoteIdentifier($schemaName));
        }

        return $schemaName;
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

    private function supportsSchemas(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
