<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->string('color', 20);
            $table->string('category')->default('general');
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_id', 'taggable_type'], 'taggables_unique_assignment');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX crm_entities_profile_data_gin ON crm_entities USING gin (profile_data jsonb_path_ops)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS crm_entities_profile_data_gin');
        }

        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
