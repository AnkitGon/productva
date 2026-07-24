<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->timestamp('setup_completed_at')->nullable()->after('name');
            $table->json('setup_progress')->nullable()->after('setup_completed_at');
        });

        // Existing tenants already configured — only new signups enter the wizard.
        DB::table('organizations')->update([
            'setup_completed_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['setup_completed_at', 'setup_progress']);
        });
    }
};
