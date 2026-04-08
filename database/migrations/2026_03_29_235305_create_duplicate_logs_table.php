<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // contact, account
            $table->string('entity_value'); // email, NIT, phone, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_logs');
    }
};
