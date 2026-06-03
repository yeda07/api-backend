<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities') || Schema::hasColumn('activities', 'priority')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities') || ! Schema::hasColumn('activities', 'priority')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
