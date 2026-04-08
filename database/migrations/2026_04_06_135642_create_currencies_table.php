<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name');
                $table->string('symbol');
                $table->integer('decimal_places')->default(2);
                $table->string('thousands_separator')->default(',');
                $table->string('decimal_separator')->default('.');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
