<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // USERS
        if (!Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        // ACCOUNTS
        if (!Schema::hasColumn('accounts', 'tenant_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        // CONTACTS
        if (!Schema::hasColumn('contacts', 'tenant_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        // RELATIONS
        if (!Schema::hasColumn('relations', 'tenant_id')) {
            Schema::table('relations', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // USERS
        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        // ACCOUNTS
        if (Schema::hasColumn('accounts', 'tenant_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        // CONTACTS
        if (Schema::hasColumn('contacts', 'tenant_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        // RELATIONS
        if (Schema::hasColumn('relations', 'tenant_id')) {
            Schema::table('relations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
