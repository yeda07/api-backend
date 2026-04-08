<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relations', function (Blueprint $table) {
            $table->id();

            // Entidad origen (Contact o Account)
            $table->string('from_type');
            $table->unsignedBigInteger('from_id');

            // Entidad destino
            $table->string('to_type');
            $table->unsignedBigInteger('to_id');

            // Tipo de relación
            $table->string('relation_type'); // manager_of, parent_of, reports_to

            $table->timestamps();

            $table->index(['from_type', 'from_id']);
            $table->index(['to_type', 'to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relations');
    }
};
