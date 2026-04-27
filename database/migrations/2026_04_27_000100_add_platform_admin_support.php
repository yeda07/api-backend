<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_platform_admin')) {
                $table->boolean('is_platform_admin')->default(false)->after('tenant_id');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'domain')) {
                $table->string('domain')->nullable()->unique()->after('name');
            }

            if (!Schema::hasColumn('tenants', 'country')) {
                $table->string('country')->nullable()->after('domain');
            }

            if (!Schema::hasColumn('tenants', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('country');
            }

            if (!Schema::hasColumn('tenants', 'status')) {
                $table->string('status')->default('ACTIVO')->after('contact_email');
            }

            if (!Schema::hasColumn('tenants', 'mrr')) {
                $table->decimal('mrr', 14, 2)->default(0)->after('status');
            }

            if (!Schema::hasColumn('tenants', 'storage_used_gb')) {
                $table->decimal('storage_used_gb', 14, 2)->default(0)->after('mrr');
            }

            if (!Schema::hasColumn('tenants', 'storage_limit_gb')) {
                $table->decimal('storage_limit_gb', 14, 2)->nullable()->after('storage_used_gb');
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'tier')) {
                $table->string('tier')->nullable()->after('max_users');
            }

            if (!Schema::hasColumn('plans', 'billing_interval')) {
                $table->string('billing_interval')->default('MENSUAL')->after('tier');
            }

            if (!Schema::hasColumn('plans', 'status')) {
                $table->string('status')->default('ACTIVO')->after('billing_interval');
            }

            if (!Schema::hasColumn('plans', 'features')) {
                $table->json('features')->nullable()->after('status');
            }
        });

        Schema::create('admin_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('name');
            $table->string('condition_text');
            $table->json('channels');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alert_rules');

        Schema::table('plans', function (Blueprint $table) {
            foreach (['features', 'status', 'billing_interval', 'tier'] as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            foreach (['storage_limit_gb', 'storage_used_gb', 'mrr', 'status', 'contact_email', 'country', 'domain'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_platform_admin')) {
                $table->dropColumn('is_platform_admin');
            }
        });
    }
};
