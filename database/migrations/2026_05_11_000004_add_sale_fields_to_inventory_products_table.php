<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->decimal('sale_price', 10, 2)->nullable()->after('cost_price');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'discount_percent']);
        });
    }
};
