<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'priority')) {
                $table->string('priority')->default('medium')->after('status');
            }

            if (!Schema::hasColumn('projects', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->after('opportunity_id')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('projects', 'estimated_hours')) {
                $table->decimal('estimated_hours', 10, 2)->default(0)->after('end_date');
            }

            if (!Schema::hasColumn('projects', 'actual_hours')) {
                $table->decimal('actual_hours', 10, 2)->default(0)->after('estimated_hours');
            }
        });

        Schema::table('project_milestones', function (Blueprint $table) {
            if (!Schema::hasColumn('project_milestones', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            if (Schema::hasColumn('project_milestones', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            foreach (['actual_hours', 'estimated_hours'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('projects', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }

            if (Schema::hasColumn('projects', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }
};
