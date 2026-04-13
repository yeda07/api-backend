<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->string('financeable_type')->nullable()->after('quotation_id');
            $table->unsignedBigInteger('financeable_id')->nullable()->after('financeable_type');
            $table->string('source_system')->default('manual')->after('record_type');
            $table->date('issued_at')->nullable()->after('currency');
            $table->date('due_at')->nullable()->after('issued_at');
            $table->decimal('outstanding_amount', 14, 2)->default(0)->after('amount');

            $table->index(['financeable_type', 'financeable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropIndex(['financeable_type', 'financeable_id']);
            $table->dropColumn([
                'financeable_type',
                'financeable_id',
                'source_system',
                'issued_at',
                'due_at',
                'outstanding_amount',
            ]);
        });
    }
};
