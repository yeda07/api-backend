<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('timezone')->default('UTC');
            $table->string('currency')->default('USD');
            $table->string('date_format')->default('Y-m-d');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'currency',
                'date_format'
            ]);
        });
    }
};
