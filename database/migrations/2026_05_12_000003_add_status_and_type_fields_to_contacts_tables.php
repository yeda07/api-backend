<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('status')->default('active')->after('address');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('status')->default('active')->after('position');
            $table->boolean('is_public_entity')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['status', 'is_public_entity']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
