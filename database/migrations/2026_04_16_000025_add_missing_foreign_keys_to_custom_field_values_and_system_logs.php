<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_values', function (Blueprint $table) {
            $table->foreign('tenant_id', 'custom_field_values_tenant_id_foreign')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('custom_field_id', 'custom_field_values_custom_field_id_foreign')
                ->references('id')
                ->on('custom_fields')
                ->cascadeOnDelete();
        });

        Schema::table('system_logs', function (Blueprint $table) {
            $table->foreign('tenant_id', 'system_logs_tenant_id_foreign')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropForeign('system_logs_tenant_id_foreign');
        });

        Schema::table('custom_field_values', function (Blueprint $table) {
            $table->dropForeign('custom_field_values_tenant_id_foreign');
            $table->dropForeign('custom_field_values_custom_field_id_foreign');
        });
    }
};
