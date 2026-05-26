<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenants', 'schema_name')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('schema_name')->nullable()->unique()->after('domain');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenants', 'schema_name')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['schema_name']);
            $table->dropColumn('schema_name');
        });
    }
};
