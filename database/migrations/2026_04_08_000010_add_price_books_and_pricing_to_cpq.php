<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_books', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
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
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('min_margin_percent', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['price_book_id', 'product_id']);
        });

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->decimal('cost_price', 14, 2)->default(0)->after('description');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('price_book_id')->nullable()->after('created_by_user_id')->constrained('price_books')->nullOnDelete();
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('list_unit_price', 14, 2)->default(0)->after('quantity');
            $table->decimal('discount_percent', 8, 2)->default(0)->after('list_unit_price');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_percent');
            $table->decimal('net_unit_price', 14, 2)->default(0)->after('discount_amount');
            $table->decimal('unit_cost', 14, 2)->default(0)->after('net_unit_price');
            $table->decimal('margin_amount', 14, 2)->default(0)->after('unit_cost');
            $table->decimal('margin_percent', 8, 2)->default(0)->after('margin_amount');
            $table->decimal('min_margin_percent', 8, 2)->default(0)->after('margin_percent');
            $table->boolean('below_min_margin')->default(false)->after('min_margin_percent');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn([
                'list_unit_price',
                'discount_percent',
                'discount_amount',
                'net_unit_price',
                'unit_cost',
                'margin_amount',
                'margin_percent',
                'min_margin_percent',
                'below_min_margin',
            ]);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_book_id');
        });

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });

        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('price_books');
    }
};
