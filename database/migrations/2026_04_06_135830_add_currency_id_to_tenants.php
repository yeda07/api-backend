<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'currency_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->foreignId('currency_id')->nullable()->constrained();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'currency_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('currency_id');
            });
        }
    }
};
