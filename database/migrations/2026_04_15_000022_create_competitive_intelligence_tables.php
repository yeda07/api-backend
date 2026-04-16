<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->string('website')->nullable();
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('battlecards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained('competitors')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('differentiators')->nullable();
            $table->json('objection_handlers')->nullable();
            $table->json('recommended_actions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->nullable()->constrained('competitors')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lossable_type')->nullable();
            $table->unsignedBigInteger('lossable_id')->nullable();
            $table->string('reason_type');
            $table->string('summary');
            $table->text('details')->nullable();
            $table->date('lost_at');
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'reason_type']);
            $table->index(['lossable_type', 'lossable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_reasons');
        Schema::dropIfExists('battlecards');
        Schema::dropIfExists('competitors');
    }
};
