<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->unsignedInteger('failed_login_attempts')->default(0);
            }

            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable();
            }

            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable();
            }

            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable();
            }

            if (!Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->json('two_factor_recovery_codes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'failed_login_attempts',
                'locked_until',
                'last_login_at',
                'last_login_ip',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
