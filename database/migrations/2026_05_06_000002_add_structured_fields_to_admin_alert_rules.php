<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_alert_rules', function (Blueprint $table) {
            $table->string('metric')->nullable()->after('condition_text');
            $table->string('operator')->nullable()->after('metric');
            $table->decimal('value', 14, 2)->nullable()->after('operator');
            $table->string('period')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('admin_alert_rules', function (Blueprint $table) {
            $table->dropColumn([
                'metric',
                'operator',
                'value',
                'period',
            ]);
        });
    }
};
