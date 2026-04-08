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
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
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
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('reorder_point')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('physical_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'warehouse_id'], 'inventory_stock_unique');
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reserved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type');
            $table->string('source_uid');
            $table->unsignedInteger('quantity');
            $table->string('status')->default('active');
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'source_type', 'source_uid']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('quantity');
            $table->text('comment')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_uid')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_products');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('inventory_categories');
    }
};
