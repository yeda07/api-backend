<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_stages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('probability_percent')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stage_id')->constrained('opportunity_stages')->cascadeOnDelete();
            $table->string('opportunityable_type')->nullable();
            $table->unsignedBigInteger('opportunityable_id')->nullable();
            $table->string('title');
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamps();

            $table->index(['opportunityable_type', 'opportunityable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('opportunity_stages');
    }
};
