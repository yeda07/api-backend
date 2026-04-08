<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // a qué entidad aplica
            $table->string('entity_type'); // Account, Contact, CrmEntity

            $table->string('name'); // etiqueta visible
            $table->string('key');  // clave técnica (slug)

            $table->string('type'); // text, number, date, select
            $table->json('options')->nullable(); // para select

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
