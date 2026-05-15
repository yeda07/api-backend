<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'default_price') && Schema::hasColumn('products', 'default_discount_percent')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'default_price')) {
                $table->decimal('default_price', 14, 2)->nullable()->after('status');
            }

            if (! Schema::hasColumn('products', 'default_discount_percent')) {
                $table->decimal('default_discount_percent', 8, 2)->nullable()->after('default_price');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'default_price') && ! Schema::hasColumn('products', 'default_discount_percent')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'default_discount_percent')) {
                $table->dropColumn('default_discount_percent');
            }

            if (Schema::hasColumn('products', 'default_price')) {
                $table->dropColumn('default_price');
            }
        });
    }
};
