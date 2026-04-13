<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_profiles', function (Blueprint $table) {
            $table->unsignedInteger('max_days_overdue')->default(0)->after('credit_limit');
            $table->boolean('auto_block')->default(true)->after('max_days_overdue');
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 10);
            $table->string('to_currency', 10);
            $table->decimal('rate', 14, 6);
            $table->date('rate_date');
            $table->timestamps();

            $table->unique(['tenant_id', 'from_currency', 'to_currency', 'rate_date'], 'exchange_rates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');

        Schema::table('credit_profiles', function (Blueprint $table) {
            $table->dropColumn(['max_days_overdue', 'auto_block']);
        });
    }
};
