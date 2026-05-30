<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'name')) {
            return;
        }

        $duplicates = DB::table('tenants')
            ->selectRaw('lower(name) as normalized_name, count(*) as total')
            ->groupByRaw('lower(name)')
            ->havingRaw('count(*) > 1')
            ->pluck('normalized_name')
            ->all();

        if ($duplicates !== []) {
            throw new RuntimeException('No se puede crear el indice unico de tenants.name porque hay nombres repetidos: '.implode(', ', $duplicates));
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS tenants_name_lower_unique ON tenants (lower(name))');

            return;
        }

        Schema::table('tenants', function ($table) {
            $table->unique('name', 'tenants_name_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS tenants_name_lower_unique');

            return;
        }

        Schema::table('tenants', function ($table) {
            $table->dropUnique('tenants_name_unique');
        });
    }
};
