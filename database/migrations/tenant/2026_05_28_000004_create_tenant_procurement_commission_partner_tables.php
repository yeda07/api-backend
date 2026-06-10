<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('reserved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type');
            $table->string('source_uid');
            $table->unsignedInteger('quantity');
            $table->string('status')->default('active');
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'source_type', 'source_uid']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('quantity');
            $table->text('comment')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_uid')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('creditable_type');
            $table->unsignedBigInteger('creditable_id');
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->unsignedInteger('max_days_overdue')->default(0);
            $table->boolean('auto_block')->default(true);
            $table->string('status')->default('ok');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'creditable_type', 'creditable_id'], 'credit_profiles_entity_unique');
            $table->index(['creditable_type', 'creditable_id']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('from_currency', 10);
            $table->string('to_currency', 10);
            $table->decimal('rate', 14, 6);
            $table->date('rate_date');
            $table->timestamps();

            $table->unique(['tenant_id', 'from_currency', 'to_currency', 'rate_date'], 'exchange_rates_unique');
        });

        Schema::create('credit_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('max_days')->default(30);
            $table->decimal('max_amount', 14, 2)->default(0);
            $table->boolean('auto_block')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('financial_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('financeable_type')->nullable();
            $table->unsignedBigInteger('financeable_id')->nullable();
            $table->string('record_type');
            $table->string('source_system')->default('manual');
            $table->string('external_reference')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('outstanding_amount', 14, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('status')->default('paid');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['financeable_type', 'financeable_id']);
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->string('customer_type')->nullable();
            $table->decimal('rate_percent', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained('quotation_items')->nullOnDelete();
            $table->foreignId('financial_record_id')->nullable()->constrained('financial_records')->nullOnDelete();
            $table->string('customer_type')->nullable();
            $table->decimal('base_amount', 14, 2);
            $table->decimal('rate_percent', 8, 2);
            $table->decimal('commission_amount', 14, 2);
            $table->string('status')->default('earned');
            $table->date('earned_at');
            $table->date('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
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
            $table->unsignedBigInteger('tenant_id');
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
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('target_amount', 14, 2);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'period']);
        });

        Schema::create('commission_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
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

        Schema::table('commission_entries', function (Blueprint $table) {
            $table->foreignId('commission_run_id')->nullable()->after('financial_record_id')->constrained('commission_runs')->nullOnDelete();
        });

        Schema::create('commission_run_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
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

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('key');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('key');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('document')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->integer('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('expenseable_type')->nullable();
            $table->unsignedBigInteger('expenseable_id')->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->string('cost_center')->nullable();
            $table->string('expense_number')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('COP');
            $table->date('expense_date');
            $table->string('status')->default('draft');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['expenseable_type', 'expenseable_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->uuid('source_uid')->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->string('cost_center')->nullable();
            $table->string('purchase_number');
            $table->string('status')->default('draft');
            $table->string('currency', 10)->default('COP');
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->date('due_date')->nullable();
            $table->date('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'purchase_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('description');
            $table->integer('quantity');
            $table->decimal('unit_cost', 14, 2);
            $table->integer('received_quantity')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->date('receipt_date');
            $table->string('reference')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('purchase_order_receipt_id')->constrained('purchase_order_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->integer('received_quantity');
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
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
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
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
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('partner_opportunity_id')->nullable()->constrained('partner_opportunities')->nullOnDelete();
            $table->foreignId('conflicting_partner_opportunity_id')->nullable()->constrained('partner_opportunities')->nullOnDelete();
            $table->text('conflict_reason');
            $table->timestamps();

            $table->index(['tenant_id', 'account_id']);
        });

        Schema::create('partner_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->unsignedBigInteger('tenant_id');
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
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
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
        Schema::dropIfExists('purchase_order_receipt_items');
        Schema::dropIfExists('purchase_order_receipts');
        Schema::dropIfExists('purchase_order_payments');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('commission_run_items');
        Schema::dropIfExists('commission_runs');
        Schema::dropIfExists('commission_targets');
        Schema::dropIfExists('commission_assignments');
        Schema::dropIfExists('commission_plan_role');
        Schema::dropIfExists('commission_plans');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('financial_records');
        Schema::dropIfExists('credit_rules');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('credit_profiles');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_reservations');
    }
};
