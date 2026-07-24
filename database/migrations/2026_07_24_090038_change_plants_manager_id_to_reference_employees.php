<?php

use Database\Support\SoftDeleteAwareUnique;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Plant manager matches employee reporting manager: an employee on the
     * active plant. Login account is optional.
     */
    public function up(): void
    {
        $plants = DB::table('plants')
            ->whereNotNull('manager_id')
            ->get(['id', 'manager_id', 'organization_id']);

        foreach ($plants as $plant) {
            $managerEmployeeId = DB::table('employees')
                ->where('user_id', $plant->manager_id)
                ->where('organization_id', $plant->organization_id)
                ->whereNull('deleted_at')
                ->value('id');

            DB::table('plants')
                ->where('id', $plant->id)
                ->update(['manager_id' => $managerEmployeeId]);
        }

        Schema::table('plants', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });

        // SQLite may rebuild the table on FK changes and turn soft-delete-aware
        // unique indexes into full unique indexes (or drop them).
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->exec('DROP INDEX IF EXISTS plants_code_unique');
            DB::connection()->getPdo()->exec('DROP INDEX IF EXISTS plants_slug_unique');

            SoftDeleteAwareUnique::create('plants', ['organization_id', 'code'], 'plants_code_unique');
            SoftDeleteAwareUnique::create('plants', ['organization_id', 'slug'], 'plants_slug_unique');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $plants = DB::table('plants')
            ->whereNotNull('manager_id')
            ->get(['id', 'manager_id']);

        foreach ($plants as $plant) {
            $managerUserId = DB::table('employees')
                ->where('id', $plant->manager_id)
                ->value('user_id');

            DB::table('plants')
                ->where('id', $plant->id)
                ->update(['manager_id' => $managerUserId]);
        }

        Schema::table('plants', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
