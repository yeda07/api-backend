<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('duplicate_logs');
    }

    public function down(): void
    {
        Schema::create('duplicate_logs', function ($table) {
            $table->id();
            $table->string('entity_type');
            $table->string('entity_value');
            $table->timestamps();
        });
    }
};
