<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'schema_migrated_at')) {
                $table->timestamp('schema_migrated_at')->nullable()->after('schema_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'schema_migrated_at')) {
                $table->dropColumn('schema_migrated_at');
            }
        });
    }
};
