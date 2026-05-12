<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lost_reasons', function (Blueprint $table) {
            $table->string('currency', 10)->nullable()->after('estimated_value');
        });
    }

    public function down(): void
    {
        Schema::table('lost_reasons', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
