<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'quotations_tenant_created_idx');
            $table->index(['tenant_id', 'status', 'created_at'], 'quotations_tenant_status_created_idx');
            $table->index(['tenant_id', 'quoteable_type', 'quoteable_id'], 'quotations_tenant_quoteable_idx');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'quotation_id'], 'quotation_items_tenant_quotation_idx');
            $table->index(['tenant_id', 'sku'], 'quotation_items_tenant_sku_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'invoices_tenant_created_idx');
            $table->index(['tenant_id', 'status', 'created_at'], 'invoices_tenant_status_created_idx');
            $table->index(['tenant_id', 'invoiceable_type', 'invoiceable_id'], 'invoices_tenant_invoiceable_idx');
        });

        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->index(['tenant_id', 'source_type', 'source_uid', 'status'], 'reservations_tenant_source_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_tenant_source_status_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_tenant_created_idx');
            $table->dropIndex('invoices_tenant_status_created_idx');
            $table->dropIndex('invoices_tenant_invoiceable_idx');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropIndex('quotation_items_tenant_quotation_idx');
            $table->dropIndex('quotation_items_tenant_sku_idx');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex('quotations_tenant_created_idx');
            $table->dropIndex('quotations_tenant_status_created_idx');
            $table->dropIndex('quotations_tenant_quoteable_idx');
        });
    }
};
