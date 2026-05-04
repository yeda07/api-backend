<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'api_calls_mes')) {
                $table->integer('api_calls_mes')->default(0)->after('storage_limit_gb');
            }

            if (!Schema::hasColumn('tenants', 'limite_api_calls')) {
                $table->integer('limite_api_calls')->default(0)->after('api_calls_mes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['limite_api_calls', 'api_calls_mes'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
