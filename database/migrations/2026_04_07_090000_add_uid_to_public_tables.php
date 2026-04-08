<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = [
        'users',
        'tenants',
        'plans',
        'accounts',
        'contacts',
        'relations',
        'crm_entities',
        'system_logs',
        'custom_fields',
        'custom_field_values',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'uid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uid')->nullable()->after('id');
                });
            }

            DB::table($tableName)
                ->whereNull('uid')
                ->orderBy('id')
                ->get(['id'])
                ->each(function ($record) use ($tableName) {
                    DB::table($tableName)
                        ->where('id', $record->id)
                        ->update(['uid' => (string) Str::uuid()]);
                });

            $indexName = "{$tableName}_uid_unique";

            if (!$this->hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->unique('uid', $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'uid')) {
                continue;
            }

            $indexName = "{$tableName}_uid_unique";

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName) {
                if ($this->hasIndex($tableName, $indexName)) {
                    $table->dropUnique($indexName);
                }

                $table->dropColumn('uid');
            });
        }
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => collect(DB::select("PRAGMA index_list('$tableName')"))
                ->contains(fn ($index) => $index->name === $indexName),
            'mysql' => collect(DB::select("SHOW INDEX FROM `$tableName`"))
                ->contains(fn ($index) => $index->Key_name === $indexName),
            'pgsql' => collect(DB::select(
                'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?',
                [$tableName]
            ))->contains(fn ($index) => $index->indexname === $indexName),
            default => false,
        };
    }
};
