<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // 🔥 MULTI-TENANT
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // 🔗 RELACIÓN CON ACCOUNT
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();

            // 📇 DATOS
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();

            $table->timestamps();

            // 🔥 ANTI-DUPLICADO POR EMPRESA
            $table->unique(['tenant_id', 'email'], 'contacts_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
