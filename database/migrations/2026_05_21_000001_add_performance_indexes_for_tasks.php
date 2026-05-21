<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'tasks_tenant_created_idx');
            $table->index(['tenant_id', 'taskable_type', 'taskable_id'], 'tasks_tenant_taskable_idx');
            $table->index(['tenant_id', 'assigned_user_id', 'status'], 'tasks_tenant_assigned_status_idx');
            $table->index(['tenant_id', 'owner_user_id', 'status'], 'tasks_tenant_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_tenant_created_idx');
            $table->dropIndex('tasks_tenant_taskable_idx');
            $table->dropIndex('tasks_tenant_assigned_status_idx');
            $table->dropIndex('tasks_tenant_owner_status_idx');
        });
    }
};
