<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'scheduled_at'], 'activities_tenant_status_scheduled_idx');
        });

        Schema::table('lost_reasons', function (Blueprint $table) {
            $table->index(['tenant_id', 'lost_at'], 'lost_reasons_tenant_lost_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lost_reasons', function (Blueprint $table) {
            $table->dropIndex('lost_reasons_tenant_lost_at_idx');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_tenant_status_scheduled_idx');
        });
    }
};
