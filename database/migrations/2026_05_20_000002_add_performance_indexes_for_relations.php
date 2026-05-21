<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relations', function (Blueprint $table) {
            $table->index(['tenant_id', 'from_type', 'from_id'], 'relations_tenant_from_idx');
            $table->index(['tenant_id', 'to_type', 'to_id'], 'relations_tenant_to_idx');
            $table->index(['tenant_id', 'relation_type'], 'relations_tenant_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('relations', function (Blueprint $table) {
            $table->dropIndex('relations_tenant_from_idx');
            $table->dropIndex('relations_tenant_to_idx');
            $table->dropIndex('relations_tenant_type_idx');
        });
    }
};
