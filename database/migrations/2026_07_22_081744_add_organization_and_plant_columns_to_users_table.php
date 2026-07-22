<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('password')->constrained('organizations')->onDelete('set null');
            $table->foreignId('active_plant_id')->nullable()->after('organization_id')->constrained('plants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['users_active_plant_id_foreign']);
            $table->dropColumn('active_plant_id');
            $table->dropForeign(['users_organization_id_foreign']);
            $table->dropColumn('organization_id');
        });
    }
};
