<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('name');
            $table->integer('payment_terms_days')->default(0)->after('address');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('expenseable_id')->constrained('cost_centers')->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('source_uid')->constrained('cost_centers')->nullOnDelete();
            $table->date('due_date')->nullable()->after('expected_at');
            $table->decimal('paid_total', 14, 2)->default(0)->after('currency');
        });

        Schema::create('purchase_order_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_payments');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn(['due_date', 'paid_total']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'payment_terms_days']);
        });

        Schema::dropIfExists('cost_centers');
    }
};
