<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->string('trigger_event')->nullable()->after('trigger_source');
            $table->index(['tenant_id', 'trigger_event', 'is_active']);
        });

        DB::table('automation_rules')
            ->whereNull('trigger_event')
            ->update(['trigger_event' => DB::raw('trigger_source')]);

        Schema::table('automation_assignment_rules', function (Blueprint $table) {
            $table->json('assigned_user_ids')->nullable()->after('assigned_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('automation_assignment_rules', function (Blueprint $table) {
            $table->dropColumn('assigned_user_ids');
        });

        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'trigger_event', 'is_active']);
            $table->dropColumn('trigger_event');
        });
    }
};
