<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('key');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('code');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->integer('physical_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'warehouse_id'], 'inventory_stock_unique');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('inventory_product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('sku');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('default_price', 14, 2)->nullable();
            $table->decimal('default_discount_percent', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'type', 'status']);
        });

        Schema::create('price_books', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('key');
            $table->string('channel')->default('B2B');
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('price_book_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('price_book_id')->constrained('price_books')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->decimal('unit_price', 14, 2);
            $table->string('currency', 10)->default('COP');
            $table->decimal('min_margin_percent', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['price_book_id', 'product_id']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('price_book_id')->nullable()->constrained('price_books')->nullOnDelete();
            $table->string('quoteable_type')->nullable();
            $table->unsignedBigInteger('quoteable_id')->nullable();
            $table->string('quote_number');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->string('currency', 10)->nullable();
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->string('local_currency', 10)->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'quote_number']);
            $table->index(['quoteable_type', 'quoteable_id']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->foreignId('catalog_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('list_unit_price', 14, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('net_unit_price', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('margin_amount', 14, 2)->default(0);
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->decimal('min_margin_percent', 8, 2)->default(0);
            $table->boolean('below_min_margin')->default(false);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
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
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('method');
            $table->string('external_reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('key');
            $table->string('website')->nullable();
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('battlecards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('competitor_id')->constrained('competitors')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('differentiators')->nullable();
            $table->json('objection_handlers')->nullable();
            $table->json('recommended_actions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('competitor_id')->nullable()->constrained('competitors')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lossable_type')->nullable();
            $table->unsignedBigInteger('lossable_id')->nullable();
            $table->string('reason_type');
            $table->string('summary');
            $table->text('details')->nullable();
            $table->date('lost_at');
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'reason_type']);
            $table->index(['tenant_id', 'lost_at'], 'lost_reasons_tenant_lost_at_idx');
            $table->index(['lossable_type', 'lossable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_reasons');
        Schema::dropIfExists('battlecards');
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('price_books');
        Schema::dropIfExists('products');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_products');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('inventory_categories');
    }
};
