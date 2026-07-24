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
     * Reporting manager is another employee on the plant. Login is optional.
     */
    public function up(): void
    {
        $employees = DB::table('employees')
            ->whereNotNull('manager_id')
            ->get(['id', 'manager_id']);

        foreach ($employees as $employee) {
            $managerEmployeeId = DB::table('employees')
                ->where('user_id', $employee->manager_id)
                ->value('id');

            DB::table('employees')
                ->where('id', $employee->id)
                ->update(['manager_id' => $managerEmployeeId]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->exec('DROP INDEX IF EXISTS employees_code_unique');
            DB::connection()->getPdo()->exec('DROP INDEX IF EXISTS employees_user_unique');

            SoftDeleteAwareUnique::create(
                'employees',
                ['organization_id', 'plant_id', 'employee_code'],
                'employees_code_unique'
            );
            SoftDeleteAwareUnique::create('employees', ['user_id'], 'employees_user_unique');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $employees = DB::table('employees')
            ->whereNotNull('manager_id')
            ->get(['id', 'manager_id']);

        foreach ($employees as $employee) {
            $managerUserId = DB::table('employees')
                ->where('id', $employee->manager_id)
                ->value('user_id');

            DB::table('employees')
                ->where('id', $employee->id)
                ->update(['manager_id' => $managerUserId]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
