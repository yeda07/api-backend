<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('sku');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'type', 'status']);
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('version');
            $table->date('release_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'version']);
        });

        Schema::create('product_dependencies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('depends_on_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('dependency_type', 20);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'depends_on_product_id', 'dependency_type'], 'product_dependency_unique');
        });

        Schema::create('account_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained('product_versions')->nullOnDelete();
            $table->date('installed_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'product_id', 'product_version_id'], 'account_product_version_unique');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('catalog_product_id')->nullable()->after('product_id')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_product_id');
        });

        Schema::dropIfExists('account_products');
        Schema::dropIfExists('product_dependencies');
        Schema::dropIfExists('product_versions');
        Schema::dropIfExists('products');
    }
};
