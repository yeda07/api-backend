<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            //  Info básica
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);

            //  Límites SaaS
            $table->integer('max_users')->nullable();
            $table->integer('max_accounts')->nullable();
            $table->integer('max_contacts')->nullable();
            $table->integer('max_entities')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
