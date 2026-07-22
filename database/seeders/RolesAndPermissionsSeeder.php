<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Permissions list
        $permissions = [
            // General / Org
            ['slug' => 'admin-dashboard', 'name' => 'Access Admin Dashboard'],
            ['slug' => 'super-admin-dashboard', 'name' => 'Access Super Admin Dashboard'],
            ['slug' => 'organization.view', 'name' => 'View Organization Details'],
            ['slug' => 'organization.update', 'name' => 'Update Organization Details'],

            // Plants
            ['slug' => 'plants.view', 'name' => 'View Plants'],
            ['slug' => 'plants.create', 'name' => 'Create Plants'],
            ['slug' => 'plants.update', 'name' => 'Update Plants'],
            ['slug' => 'plants.delete', 'name' => 'Delete Plants'],
            ['slug' => 'plants.export', 'name' => 'Export Plants Data'],

            // Warehouses
            ['slug' => 'warehouses.view', 'name' => 'View Warehouses'],
            ['slug' => 'warehouses.create', 'name' => 'Create Warehouses'],
            ['slug' => 'warehouses.update', 'name' => 'Update Warehouses'],
            ['slug' => 'warehouses.delete', 'name' => 'Delete Warehouses'],
            ['slug' => 'warehouses.export', 'name' => 'Export Warehouses Data'],

            // Inventory
            ['slug' => 'inventory.view', 'name' => 'View Inventory'],
            ['slug' => 'inventory.adjust', 'name' => 'Adjust Inventory Levels'],

            // Products
            ['slug' => 'products.view', 'name' => 'View Products'],
            ['slug' => 'products.create', 'name' => 'Create Products'],
            ['slug' => 'products.update', 'name' => 'Update Products'],
            ['slug' => 'products.delete', 'name' => 'Delete Products'],
            ['slug' => 'products.export', 'name' => 'Export Products Data'],

            // Production
            ['slug' => 'production.view', 'name' => 'View Production Orders'],
            ['slug' => 'production.create', 'name' => 'Create Production Orders'],
            ['slug' => 'production.update', 'name' => 'Update Production Orders'],
            ['slug' => 'production-orders.release', 'name' => 'Release Production Orders'],

            // Quality
            ['slug' => 'quality.view', 'name' => 'View Quality Control Logs'],
            ['slug' => 'quality.verify', 'name' => 'Verify Quality Status'],

            // Maintenance
            ['slug' => 'maintenance.view', 'name' => 'View Maintenance Schedules'],
            ['slug' => 'maintenance.manage', 'name' => 'Manage Maintenance Tasks'],

            // Procurement
            ['slug' => 'procurement.view', 'name' => 'View Procurement Orders'],
            ['slug' => 'procurement.manage', 'name' => 'Manage Procurement Orders'],

            // Sales
            ['slug' => 'sales.view', 'name' => 'View Sales Orders'],
            ['slug' => 'sales.manage', 'name' => 'Manage Sales Orders'],

            // Reports
            ['slug' => 'reports.view', 'name' => 'View Reports'],
            ['slug' => 'reports.export', 'name' => 'Export Reports Data'],

            // Settings / System
            ['slug' => 'settings.manage', 'name' => 'Manage General Settings'],
            ['slug' => 'integrations.manage', 'name' => 'Manage Integrations'],
            ['slug' => 'system.backup', 'name' => 'Trigger Database Backup'],
            ['slug' => 'system.config', 'name' => 'Manage System Configuration'],

            // Users
            ['slug' => 'users.view', 'name' => 'View Users'],
            ['slug' => 'users.invite', 'name' => 'Invite New Users'],
            ['slug' => 'users.update', 'name' => 'Update Users Details'],
            ['slug' => 'users.delete', 'name' => 'Delete Users'],

            // Roles
            ['slug' => 'roles.view', 'name' => 'View Roles & Permissions'],
            ['slug' => 'roles.create', 'name' => 'Create Roles'],
            ['slug' => 'roles.update', 'name' => 'Update Roles & Permissions'],
            ['slug' => 'roles.delete', 'name' => 'Delete Roles'],

            // Audit Logs
            ['slug' => 'audit.view', 'name' => 'View Audit Logs'],

            // Employees
            ['slug' => 'employees.view', 'name' => 'View Employees'],
            ['slug' => 'employees.create', 'name' => 'Create Employees'],
            ['slug' => 'employees.update', 'name' => 'Update Employees'],
            ['slug' => 'employees.delete', 'name' => 'Delete Employees'],
            ['slug' => 'employees.export', 'name' => 'Export Employees'],
            ['slug' => 'employees.manage', 'name' => 'Manage Employees'],

            // Departments
            ['slug' => 'departments.view', 'name' => 'View Departments'],
            ['slug' => 'departments.create', 'name' => 'Create Departments'],
            ['slug' => 'departments.update', 'name' => 'Update Departments'],
            ['slug' => 'departments.delete', 'name' => 'Delete Departments'],

            // Shifts
            ['slug' => 'shift.view', 'name' => 'View Shifts'],
            ['slug' => 'shift.create', 'name' => 'Create Shifts'],
            ['slug' => 'shift.update', 'name' => 'Update Shifts'],
            ['slug' => 'shift.delete', 'name' => 'Delete Shifts'],

            // Work Centers
            ['slug' => 'work-centers.view', 'name' => 'View Work Centers'],
            ['slug' => 'work-centers.create', 'name' => 'Create Work Centers'],
            ['slug' => 'work-centers.update', 'name' => 'Update Work Centers'],
            ['slug' => 'work-centers.delete', 'name' => 'Delete Work Centers'],
        ];

        $permissionIds = [];
        foreach ($permissions as $p) {
            $created = Permission::firstOrCreate(
                ['slug' => $p['slug']],
                ['name' => $p['name']]
            );
            $permissionIds[$p['slug']] = $created->id;
        }

        // 2. Define default roles and their associated permission slugs
        $defaultRoles = [
            'super-admin' => [
                'name' => 'Super Admin',
                'permissions' => array_keys($permissionIds), // all permissions
            ],
            'admin' => [
                'name' => 'Administrator',
                'permissions' => [
                    'admin-dashboard', 'organization.view', 'organization.update',
                    'plants.view', 'plants.create', 'plants.update', 'plants.delete', 'plants.export',
                    'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete', 'warehouses.export',
                    'inventory.view', 'inventory.adjust',
                    'products.view', 'products.create', 'products.update', 'products.delete', 'products.export',
                    'production.view', 'production.create', 'production.update', 'production-orders.release',
                    'quality.view', 'quality.verify',
                    'maintenance.view', 'maintenance.manage',
                    'procurement.view', 'procurement.manage',
                    'sales.view', 'sales.manage',
                    'reports.view', 'reports.export',
                    'settings.manage', 'integrations.manage', 'system.backup', 'system.config',
                    'users.view', 'users.invite', 'users.update', 'users.delete',
                    'roles.view', 'roles.create', 'roles.update', 'roles.delete',
                    'audit.view',
                    'employees.view', 'employees.create', 'employees.update', 'employees.delete', 'employees.export', 'employees.manage',
                    'departments.view', 'departments.create', 'departments.update', 'departments.delete',
                    'shift.view', 'shift.create', 'shift.update', 'shift.delete',
                    'work-centers.view', 'work-centers.create', 'work-centers.update', 'work-centers.delete',
                ],
            ],
            'production-manager' => [
                'name' => 'Production Manager',
                'permissions' => [
                    'admin-dashboard',
                    'plants.view',
                    'products.view',
                    'production.view', 'production.create', 'production.update', 'production-orders.release',
                    'quality.view', 'quality.verify',
                    'reports.view', 'reports.export',
                ],
            ],
            'warehouse-manager' => [
                'name' => 'Warehouse Manager',
                'permissions' => [
                    'admin-dashboard',
                    'plants.view',
                    'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.export',
                    'inventory.view', 'inventory.adjust',
                    'products.view',
                    'reports.view', 'reports.export',
                ],
            ],
            'inventory-manager' => [
                'name' => 'Inventory Manager',
                'permissions' => [
                    'admin-dashboard',
                    'inventory.view', 'inventory.adjust',
                    'products.view',
                    'reports.view',
                ],
            ],
            'quality-manager' => [
                'name' => 'Quality Manager',
                'permissions' => [
                    'admin-dashboard',
                    'products.view',
                    'production.view',
                    'quality.view', 'quality.verify',
                    'reports.view',
                ],
            ],
            'operator' => [
                'name' => 'Operator',
                'permissions' => [
                    'admin-dashboard',
                    'production.view',
                    'quality.view',
                ],
            ],
            'maintenance-engineer' => [
                'name' => 'Maintenance Engineer',
                'permissions' => [
                    'admin-dashboard',
                    'maintenance.view', 'maintenance.manage',
                ],
            ],
            'procurement-manager' => [
                'name' => 'Procurement Manager',
                'permissions' => [
                    'admin-dashboard',
                    'procurement.view', 'procurement.manage',
                    'inventory.view',
                ],
            ],
            'sales-manager' => [
                'name' => 'Sales Manager',
                'permissions' => [
                    'admin-dashboard',
                    'sales.view', 'sales.manage',
                    'inventory.view',
                ],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'permissions' => [
                    'admin-dashboard',
                    'organization.view',
                    'plants.view',
                    'warehouses.view',
                    'inventory.view',
                    'products.view',
                    'production.view',
                    'quality.view',
                    'maintenance.view',
                    'procurement.view',
                    'sales.view',
                    'reports.view',
                ],
            ],
        ];

        foreach ($defaultRoles as $slug => $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $roleData['name']]
            );

            // Sync role permissions
            $syncIds = [];
            foreach ($roleData['permissions'] as $pSlug) {
                if (isset($permissionIds[$pSlug])) {
                    $syncIds[] = $permissionIds[$pSlug];
                }
            }
            $role->permissions()->sync($syncIds);
        }

        // 3. Create Users and Assign Roles
        $superAdminRole = Role::where('slug', 'super-admin')->first();
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

        $adminRole = Role::where('slug', 'admin')->first();
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

        // Seed default departments
        $departmentsData = ['Production', 'Logistics', 'Quality Control', 'Maintenance'];
        $departments = [];
        foreach ($departmentsData as $deptName) {
            $departments[] = Department::firstOrCreate([
                'name' => $deptName,
                'organization_id' => $defaultOrg->id,
            ]);
        }

        // Seed sample employees
        $employeesData = [
            [
                'employee_code' => 'EMP-001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'job_title' => 'Production Manager',
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
