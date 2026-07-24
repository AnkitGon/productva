<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class DefaultRoles
{
    /**
     * Role slugs that used to ship with the app and should be removed.
     *
     * @var list<string>
     */
    public const REMOVED_SLUGS = [
        'inventory-manager',
        'operator',
        'maintenance-engineer',
        'procurement-manager',
    ];

    /**
     * @return list<array{slug: string, name: string}>
     */
    public static function permissions(): array
    {
        return [
            ['slug' => 'admin-dashboard', 'name' => 'Access Admin Dashboard'],
            ['slug' => 'super-admin-dashboard', 'name' => 'Access Super Admin Dashboard'],
            ['slug' => 'organization.view', 'name' => 'View Organization Details'],
            ['slug' => 'organization.update', 'name' => 'Update Organization Details'],
            ['slug' => 'plants.view', 'name' => 'View Plants'],
            ['slug' => 'plants.create', 'name' => 'Create Plants'],
            ['slug' => 'plants.update', 'name' => 'Update Plants'],
            ['slug' => 'plants.delete', 'name' => 'Delete Plants'],
            ['slug' => 'plants.export', 'name' => 'Export Plants Data'],
            ['slug' => 'warehouses.view', 'name' => 'View Warehouses'],
            ['slug' => 'warehouses.create', 'name' => 'Create Warehouses'],
            ['slug' => 'warehouses.update', 'name' => 'Update Warehouses'],
            ['slug' => 'warehouses.delete', 'name' => 'Delete Warehouses'],
            ['slug' => 'warehouses.export', 'name' => 'Export Warehouses Data'],
            ['slug' => 'inventory.view', 'name' => 'View Inventory'],
            ['slug' => 'inventory.adjust', 'name' => 'Adjust Inventory Levels'],
            ['slug' => 'inventory.export', 'name' => 'Export Inventory Data'],
            ['slug' => 'inventory.history', 'name' => 'View Inventory History'],
            ['slug' => 'products.view', 'name' => 'View Products'],
            ['slug' => 'products.create', 'name' => 'Create Products'],
            ['slug' => 'products.update', 'name' => 'Update Products'],
            ['slug' => 'products.delete', 'name' => 'Delete Products'],
            ['slug' => 'products.import', 'name' => 'Import Products Data'],
            ['slug' => 'products.export', 'name' => 'Export Products Data'],
            ['slug' => 'boms.view', 'name' => 'View Bills of Materials'],
            ['slug' => 'boms.create', 'name' => 'Create Bills of Materials'],
            ['slug' => 'boms.update', 'name' => 'Update Bills of Materials'],
            ['slug' => 'boms.delete', 'name' => 'Delete Bills of Materials'],
            ['slug' => 'operations.view', 'name' => 'View Operations'],
            ['slug' => 'operations.create', 'name' => 'Create Operations'],
            ['slug' => 'operations.update', 'name' => 'Update Operations'],
            ['slug' => 'operations.delete', 'name' => 'Delete Operations'],
            ['slug' => 'routing.view', 'name' => 'View Routings'],
            ['slug' => 'routing.create', 'name' => 'Create Routings'],
            ['slug' => 'routing.update', 'name' => 'Update Routings'],
            ['slug' => 'routing.delete', 'name' => 'Delete Routings'],
            ['slug' => 'production.view', 'name' => 'View Production Orders'],
            ['slug' => 'production.create', 'name' => 'Create Production Orders'],
            ['slug' => 'production.update', 'name' => 'Update Production Orders'],
            ['slug' => 'production-orders.release', 'name' => 'Release Production Orders'],
            ['slug' => 'quality.view', 'name' => 'View Quality Control Logs'],
            ['slug' => 'quality.verify', 'name' => 'Verify Quality Status'],
            ['slug' => 'maintenance.view', 'name' => 'View Maintenance Schedules'],
            ['slug' => 'maintenance.manage', 'name' => 'Manage Maintenance Tasks'],
            ['slug' => 'procurement.view', 'name' => 'View Procurement Orders'],
            ['slug' => 'procurement.manage', 'name' => 'Manage Procurement Orders'],
            ['slug' => 'sales.view', 'name' => 'View Sales Orders'],
            ['slug' => 'sales.manage', 'name' => 'Manage Sales Orders'],
            ['slug' => 'reports.view', 'name' => 'View Reports'],
            ['slug' => 'reports.export', 'name' => 'Export Reports Data'],
            ['slug' => 'settings.manage', 'name' => 'Manage General Settings'],
            ['slug' => 'integrations.manage', 'name' => 'Manage Integrations'],
            ['slug' => 'system.backup', 'name' => 'Trigger Database Backup'],
            ['slug' => 'system.config', 'name' => 'Manage System Configuration'],
            ['slug' => 'users.view', 'name' => 'View Users'],
            ['slug' => 'users.invite', 'name' => 'Invite New Users'],
            ['slug' => 'users.update', 'name' => 'Update Users Details'],
            ['slug' => 'users.delete', 'name' => 'Delete Users'],
            ['slug' => 'roles.view', 'name' => 'View Roles & Permissions'],
            ['slug' => 'roles.create', 'name' => 'Create Roles'],
            ['slug' => 'roles.update', 'name' => 'Update Roles & Permissions'],
            ['slug' => 'roles.delete', 'name' => 'Delete Roles'],
            ['slug' => 'audit.view', 'name' => 'View Audit Logs'],
            ['slug' => 'employees.view', 'name' => 'View Employees'],
            ['slug' => 'employees.create', 'name' => 'Create Employees'],
            ['slug' => 'employees.update', 'name' => 'Update Employees'],
            ['slug' => 'employees.delete', 'name' => 'Delete Employees'],
            ['slug' => 'employees.export', 'name' => 'Export Employees'],
            ['slug' => 'employees.manage', 'name' => 'Manage Employees'],
            ['slug' => 'departments.view', 'name' => 'View Departments'],
            ['slug' => 'departments.create', 'name' => 'Create Departments'],
            ['slug' => 'departments.update', 'name' => 'Update Departments'],
            ['slug' => 'departments.delete', 'name' => 'Delete Departments'],
            ['slug' => 'shift.view', 'name' => 'View Shifts'],
            ['slug' => 'shift.create', 'name' => 'Create Shifts'],
            ['slug' => 'shift.update', 'name' => 'Update Shifts'],
            ['slug' => 'shift.delete', 'name' => 'Delete Shifts'],
            ['slug' => 'work-centers.view', 'name' => 'View Work Centers'],
            ['slug' => 'work-centers.create', 'name' => 'Create Work Centers'],
            ['slug' => 'work-centers.update', 'name' => 'Update Work Centers'],
            ['slug' => 'work-centers.delete', 'name' => 'Delete Work Centers'],
            ['slug' => 'machine.view', 'name' => 'View Machines'],
            ['slug' => 'machine.create', 'name' => 'Create Machines'],
            ['slug' => 'machine.update', 'name' => 'Update Machines'],
            ['slug' => 'machine.delete', 'name' => 'Delete Machines'],
            ['slug' => 'uom.view', 'name' => 'View Units of Measure'],
            ['slug' => 'uom.create', 'name' => 'Create Units of Measure'],
            ['slug' => 'uom.update', 'name' => 'Update Units of Measure'],
            ['slug' => 'uom.delete', 'name' => 'Delete Units of Measure'],
            ['slug' => 'product-category.view', 'name' => 'View Product Categories'],
            ['slug' => 'product-category.create', 'name' => 'Create Product Categories'],
            ['slug' => 'product-category.update', 'name' => 'Update Product Categories'],
            ['slug' => 'product-category.delete', 'name' => 'Delete Product Categories'],
        ];
    }

    /**
     * @return array<string, array{name: string, permissions: list<string>|null}>
     */
    public static function roles(): array
    {
        return [
            'super-admin' => [
                'name' => 'Super Admin',
                'permissions' => null, // all permissions
            ],
            'admin' => [
                'name' => 'Administrator',
                'permissions' => [
                    'admin-dashboard', 'organization.view', 'organization.update',
                    'plants.view', 'plants.create', 'plants.update', 'plants.delete', 'plants.export',
                    'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete', 'warehouses.export',
                    'inventory.view', 'inventory.adjust', 'inventory.export', 'inventory.history',
                    'products.view', 'products.create', 'products.update', 'products.delete', 'products.import', 'products.export',
                    'boms.view', 'boms.create', 'boms.update', 'boms.delete',
                    'operations.view', 'operations.create', 'operations.update', 'operations.delete',
                    'routing.view', 'routing.create', 'routing.update', 'routing.delete',
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
                    'machine.view', 'machine.create', 'machine.update', 'machine.delete',
                    'uom.view', 'uom.create', 'uom.update', 'uom.delete',
                    'product-category.view', 'product-category.create', 'product-category.update', 'product-category.delete',
                ],
            ],
            'plant-manager' => [
                'name' => 'Plant Manager',
                'permissions' => [
                    'admin-dashboard',
                    'organization.view',
                    'plants.view', 'plants.update',
                    'employees.view', 'employees.create', 'employees.update', 'employees.export',
                    'departments.view', 'departments.create', 'departments.update',
                    'shift.view', 'shift.create', 'shift.update',
                    'work-centers.view', 'work-centers.create', 'work-centers.update',
                    'machine.view', 'machine.create', 'machine.update',
                    'production.view', 'production.create', 'production.update', 'production-orders.release',
                    'inventory.view',
                    'reports.view', 'reports.export',
                ],
            ],
            'production-manager' => [
                'name' => 'Production Manager',
                'permissions' => [
                    'admin-dashboard',
                    'plants.view',
                    'products.view',
                    'boms.view', 'boms.create', 'boms.update', 'boms.delete',
                    'operations.view', 'operations.create', 'operations.update', 'operations.delete',
                    'routing.view', 'routing.create', 'routing.update', 'routing.delete',
                    'production.view', 'production.create', 'production.update', 'production-orders.release',
                    'work-centers.view', 'work-centers.create', 'work-centers.update',
                    'machine.view', 'machine.create', 'machine.update',
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
                    'inventory.view', 'inventory.adjust', 'inventory.export', 'inventory.history',
                    'products.view',
                    'reports.view', 'reports.export',
                ],
            ],
            'quality-manager' => [
                'name' => 'Quality Manager',
                'permissions' => [
                    'admin-dashboard',
                    'products.view',
                    'production.view',
                    'operations.view',
                    'quality.view', 'quality.verify',
                    'reports.view',
                ],
            ],
            'maintenance-manager' => [
                'name' => 'Maintenance Manager',
                'permissions' => [
                    'admin-dashboard',
                    'plants.view',
                    'machine.view', 'machine.update',
                    'work-centers.view',
                    'maintenance.view', 'maintenance.manage',
                    'reports.view',
                ],
            ],
            'purchasing-manager' => [
                'name' => 'Purchasing Manager',
                'permissions' => [
                    'admin-dashboard',
                    'procurement.view', 'procurement.manage',
                    'inventory.view',
                    'products.view',
                    'reports.view',
                ],
            ],
            'sales-manager' => [
                'name' => 'Sales Manager',
                'permissions' => [
                    'admin-dashboard',
                    'sales.view', 'sales.manage',
                    'inventory.view',
                    'products.view',
                    'reports.view',
                ],
            ],
            'finance-manager' => [
                'name' => 'Finance Manager',
                'permissions' => [
                    'admin-dashboard',
                    'organization.view',
                    'products.view',
                    'inventory.view',
                    'procurement.view',
                    'sales.view',
                    'reports.view', 'reports.export',
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
                    'boms.view',
                    'operations.view',
                    'routing.view',
                    'production.view',
                    'quality.view',
                    'maintenance.view',
                    'procurement.view',
                    'sales.view',
                    'reports.view',
                    'employees.view',
                    'departments.view',
                    'shift.view',
                    'work-centers.view',
                    'machine.view',
                ],
            ],
        ];
    }

    /**
     * Ensure permissions and default roles exist, and remove obsolete roles.
     *
     * @return array<string, Role>
     */
    public static function ensure(): array
    {
        $permissionIds = [];

        foreach (self::permissions() as $permission) {
            $created = Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                ['name' => $permission['name']]
            );
            $permissionIds[$permission['slug']] = $created->id;
        }

        $roles = [];

        foreach (self::roles() as $slug => $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $roleData['name']]
            );

            if ($role->name !== $roleData['name']) {
                $role->update(['name' => $roleData['name']]);
            }

            $permissionSlugs = $roleData['permissions'] ?? array_keys($permissionIds);
            $syncIds = [];
            foreach ($permissionSlugs as $permissionSlug) {
                if (isset($permissionIds[$permissionSlug])) {
                    $syncIds[] = $permissionIds[$permissionSlug];
                }
            }
            $role->permissions()->sync($syncIds);
            $roles[$slug] = $role;
        }

        self::removeObsoleteRoles();

        return $roles;
    }

    public static function removeObsoleteRoles(): void
    {
        $obsoleteRoles = Role::query()
            ->whereIn('slug', self::REMOVED_SLUGS)
            ->get();

        if ($obsoleteRoles->isEmpty()) {
            return;
        }

        $ids = $obsoleteRoles->pluck('id')->all();

        DB::table('permission_role')->whereIn('role_id', $ids)->delete();
        DB::table('role_user')->whereIn('role_id', $ids)->delete();
        Role::query()->whereIn('id', $ids)->delete();
    }
}
