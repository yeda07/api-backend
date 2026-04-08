<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('relations', function (Blueprint $table) {
            $table->unique(
                [
                    'tenant_id',
                    'from_type',
                    'from_id',
                    'to_type',
                    'to_id',
                    'relation_type'
                ],
                'unique_relation_per_tenant'
            );
        });
    }

    public function down(): void
    {
        Schema::table('relations', function (Blueprint $table) {
            $table->dropUnique('unique_relation_per_tenant');
        });
    }
};
