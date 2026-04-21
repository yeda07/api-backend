<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('validity_days')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_required', 'is_active']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->after('account_id')->constrained('document_types')->nullOnDelete();
            $table->date('issue_date')->nullable()->after('size');
            $table->date('expiration_date')->nullable()->after('issue_date');
            $table->string('status')->default('valid')->after('expiration_date');
            $table->boolean('is_active')->default(true)->after('status');
            $table->unsignedInteger('version_number')->default(1)->after('is_active');
            $table->timestamp('replaced_at')->nullable()->after('version_number');

            $table->unique(['tenant_id', 'account_id', 'document_type_id'], 'documents_account_type_unique');
            $table->index(['tenant_id', 'status', 'expiration_date']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('status')->default('valid');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->unsignedInteger('days_before');
            $table->string('notification_channel')->default('system');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'document_type_id', 'days_before', 'notification_channel'], 'alert_rules_unique');
        });

        Schema::create('document_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('alert_rule_id')->nullable()->constrained('alert_rules')->nullOnDelete();
            $table->date('alert_date');
            $table->string('notification_channel')->default('system');
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'alert_date']);
            $table->index(['tenant_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_alerts');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('document_versions');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('documents_account_type_unique');
            $table->dropIndex(['tenant_id', 'status', 'expiration_date']);
            $table->dropConstrainedForeignId('document_type_id');
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn([
                'issue_date',
                'expiration_date',
                'status',
                'is_active',
                'version_number',
                'replaced_at',
            ]);
        });

        Schema::dropIfExists('document_types');
    }
};
