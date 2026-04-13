<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_book_items', function (Blueprint $table) {
            $table->string('currency', 10)->default('COP')->after('unit_price');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('exchange_rate', 14, 6)->default(1)->after('currency');
            $table->string('local_currency', 10)->nullable()->after('exchange_rate');
        });

        Schema::create('credit_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('creditable_type');
            $table->unsignedBigInteger('creditable_id');
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->string('status')->default('ok');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'creditable_type', 'creditable_id'], 'credit_profiles_entity_unique');
            $table->index(['creditable_type', 'creditable_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('invoiceable_type');
            $table->unsignedBigInteger('invoiceable_id');
            $table->string('invoice_number');
            $table->string('status')->default('draft');
            $table->string('quote_currency', 10)->nullable();
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->string('currency', 10);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('outstanding_total', 14, 2)->default(0);
            $table->date('issued_at')->nullable();
            $table->date('due_date')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['invoiceable_type', 'invoiceable_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('method');
            $table->string('external_reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('credit_profiles');

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'local_currency']);
        });

        Schema::table('price_book_items', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
