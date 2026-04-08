<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            // MULTI-TENANT
            $table->unsignedBigInteger('tenant_id');

            $table->string('name');

            // SIN unique global
            $table->string('document');

            $table->string('email')->nullable();
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            $table->timestamps();

            // UNIQUE POR EMPRESA
            $table->unique(['tenant_id', 'document'], 'accounts_document_unique');
            $table->unique(['tenant_id', 'email'], 'accounts_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
