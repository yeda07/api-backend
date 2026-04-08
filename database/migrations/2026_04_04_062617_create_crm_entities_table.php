<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_entities', function (Blueprint $table) {
            $table->id();

            // 🔥 MULTI-TENANT
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // 🧠 TIPO DE ENTIDAD
            $table->enum('type', ['B2B', 'B2C', 'B2G']);

            // 📦 DATA FLEXIBLE
            $table->json('profile_data');

            $table->timestamps();

            // 🔥 ÍNDICES ÚTILES
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_entities');
    }
};
