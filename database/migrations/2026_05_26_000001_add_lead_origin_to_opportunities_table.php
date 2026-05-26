<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('opportunities', 'lead_origin')) {
            return;
        }

        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('lead_origin')->nullable()->after('email');
            $table->index(['tenant_id', 'lead_origin'], 'opportunities_tenant_lead_origin_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('opportunities', 'lead_origin')) {
            return;
        }

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex('opportunities_tenant_lead_origin_idx');
            $table->dropColumn('lead_origin');
        });
    }
};
