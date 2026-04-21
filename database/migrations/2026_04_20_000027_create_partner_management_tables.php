<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            $table->json('contact_info')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('partner_opportunities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('open');
            $table->string('conflict_scope')->default('global');
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'account_id', 'status']);
            $table->index(['tenant_id', 'partner_id', 'status']);
        });

        Schema::create('opportunity_conflicts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_opportunity_id')->nullable()->constrained('partner_opportunities')->nullOnDelete();
            $table->foreignId('conflicting_partner_opportunity_id')->nullable()->constrained('partner_opportunities')->nullOnDelete();
            $table->text('conflict_reason');
            $table->timestamps();

            $table->index(['tenant_id', 'account_id']);
        });

        Schema::create('partner_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->string('disk');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'is_active']);
        });

        Schema::create('partner_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_resource_id')->constrained('partner_resources')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['partner_id', 'partner_resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_access');
        Schema::dropIfExists('partner_resources');
        Schema::dropIfExists('opportunity_conflicts');
        Schema::dropIfExists('partner_opportunities');
        Schema::dropIfExists('partners');
    }
};
