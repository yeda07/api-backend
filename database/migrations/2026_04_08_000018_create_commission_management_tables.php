<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->decimal('base_percent', 8, 2)->default(0);
            $table->json('tiers_json')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('commission_plan_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_plan_id')->constrained('commission_plans')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['commission_plan_id', 'role_id']);
        });

        Schema::create('commission_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('commission_plan_id')->constrained('commission_plans')->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('commission_targets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('target_amount', 14, 2);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'period']);
        });

        Schema::create('commission_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('commission_plan_id')->nullable()->constrained('commission_plans')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('sales_amount', 14, 2)->default(0);
            $table->decimal('margin_amount', 14, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->string('status')->default('pending');
            $table->date('approved_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_run_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_run_id')->constrained('commission_runs')->cascadeOnDelete();
            $table->foreignId('commission_entry_id')->nullable()->constrained('commission_entries')->nullOnDelete();
            $table->string('source_type');
            $table->uuid('source_uid');
            $table->decimal('base_amount', 14, 2);
            $table->decimal('applied_percent', 8, 2);
            $table->decimal('commission_amount', 14, 2);
            $table->json('rule_snapshot_json')->nullable();
            $table->timestamps();
        });

        Schema::table('commission_entries', function (Blueprint $table) {
            $table->foreignId('commission_run_id')->nullable()->after('financial_record_id')->constrained('commission_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_run_id');
        });

        Schema::dropIfExists('commission_run_items');
        Schema::dropIfExists('commission_runs');
        Schema::dropIfExists('commission_targets');
        Schema::dropIfExists('commission_assignments');
        Schema::dropIfExists('commission_plan_role');
        Schema::dropIfExists('commission_plans');
    }
};
