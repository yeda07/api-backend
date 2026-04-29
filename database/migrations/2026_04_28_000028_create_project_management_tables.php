<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique('opportunity_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();

            $table->index(['project_id', 'order']);
        });

        Schema::create('project_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->decimal('hours_allocated', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_assignments');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');
    }
};
