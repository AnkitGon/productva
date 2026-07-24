<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use App\Support\DefaultRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = DefaultRoles::ensure();

        $superAdminRole = $roles['super-admin'] ?? Role::where('slug', 'super-admin')->first();
        $superAdminUser = User::firstOrCreate([
            'email' => 'superadmin@example.com',
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $superAdminUser->roles()->syncWithoutDetaching([$superAdminRole->id]);

        $defaultOrg = Organization::firstOrCreate([
            'name' => 'Default Organization',
        ]);
        $defaultPlant = Plant::firstOrCreate([
            'organization_id' => $defaultOrg->id,
            'code' => 'DFT',
            'slug' => 'default-plant',
        ], [
            'name' => 'Default Plant',
            'status' => 'Active',
            'is_default' => true,
        ]);

        $adminRole = $roles['admin'] ?? Role::where('slug', 'admin')->first();
        $adminUser = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'organization_id' => $defaultOrg->id,
            'active_plant_id' => $defaultPlant->id,
        ]);

        if (! $adminUser->organization_id) {
            $adminUser->update([
                'organization_id' => $defaultOrg->id,
                'active_plant_id' => $defaultPlant->id,
            ]);
        }

        $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);

        $departmentsData = [
            ['name' => 'Production', 'code' => 'PROD'],
            ['name' => 'Logistics', 'code' => 'LOG'],
            ['name' => 'Quality Control', 'code' => 'QC'],
            ['name' => 'Maintenance', 'code' => 'MNT'],
        ];
        $departments = [];
        foreach ($departmentsData as $dept) {
            $departments[] = Department::firstOrCreate([
                'organization_id' => $defaultOrg->id,
                'plant_id' => $defaultPlant->id,
                'code' => $dept['code'],
            ], [
                'name' => $dept['name'],
                'status' => 'Active',
            ]);
        }

        $employeesData = [
            [
                'employee_code' => 'EMP-001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'job_title' => 'Production Supervisor',
                'department_id' => $departments[0]->id,
                'email' => 'john.doe@example.com',
                'employment_type' => 'Full-Time',
                'status' => 'Active',
            ],
            [
                'employee_code' => 'EMP-002',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'job_title' => 'Warehouse Supervisor',
                'department_id' => $departments[1]->id,
                'email' => 'jane.smith@example.com',
                'employment_type' => 'Full-Time',
                'status' => 'Active',
            ],
        ];

        foreach ($employeesData as $emp) {
            Employee::firstOrCreate([
                'organization_id' => $defaultOrg->id,
                'employee_code' => $emp['employee_code'],
            ], [
                'first_name' => $emp['first_name'],
                'last_name' => $emp['last_name'],
                'job_title' => $emp['job_title'],
                'department_id' => $emp['department_id'],
                'plant_id' => $defaultPlant->id,
                'email' => $emp['email'],
                'employment_type' => $emp['employment_type'],
                'status' => $emp['status'],
            ]);
        }
    }
}
