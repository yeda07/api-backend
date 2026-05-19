<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'invoice_id')) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('opportunity_id')
                    ->constrained('invoices')
                    ->nullOnDelete();

                $table->unique('invoice_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'invoice_id')) {
                $table->dropUnique(['invoice_id']);
                $table->dropConstrainedForeignId('invoice_id');
            }
        });
    }
};
