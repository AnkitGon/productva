<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Permission Boundary Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'PB1',
        'slug' => 'permission-boundary-plant',
    ], [
        'name' => 'Permission Boundary Plant',
        'is_default' => true,
    ]);

    $this->department = Department::create([
        'name' => 'Boundary Department',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
    ]);
});

function userWithPermissions(array $permissionSlugs, Organization $org, Plant $plant): User
{
    $role = Role::create([
        'name' => 'Custom '.uniqid(),
        'slug' => 'custom-'.uniqid(),
        'description' => 'Permission boundary test role',
    ]);

    $permissionIds = Permission::query()
        ->whereIn('slug', $permissionSlugs)
        ->pluck('id')
        ->all();

    $role->permissions()->sync($permissionIds);

    $user = User::factory()->create([
        'organization_id' => $org->id,
        'active_plant_id' => $plant->id,
    ]);
    $user->roles()->attach($role);

    return $user;
}

test('employee view-only users can list but cannot create update or delete employees', function () {
    $user = userWithPermissions(
        ['admin-dashboard', 'employees.view'],
        $this->org,
        $this->plant,
    );

    $employee = Employee::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'department_id' => $this->department->id,
        'employee_code' => 'EMP-PB-1',
        'first_name' => 'View',
        'last_name' => 'Only',
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $this->actingAs($user)->get(route('employees.index'))->assertOk();
    $this->actingAs($user)->get(route('employees.show', $employee))->assertOk();

    $this->actingAs($user)->post(route('employees.store'), [
        'employee_code' => 'EMP-PB-2',
        'first_name' => 'Nope',
        'last_name' => 'Create',
        'department_id' => $this->department->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ])->assertForbidden();

    $this->actingAs($user)->put(route('employees.update', $employee), [
        'employee_code' => 'EMP-PB-1',
        'first_name' => 'Changed',
        'last_name' => 'Only',
        'department_id' => $this->department->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('employees.destroy', $employee))->assertForbidden();
});

test('department view-only users can list but cannot mutate departments', function () {
    $user = userWithPermissions(
        ['admin-dashboard', 'departments.view'],
        $this->org,
        $this->plant,
    );

    $this->actingAs($user)->get(route('departments.index'))->assertOk();

    $this->actingAs($user)->post(route('departments.store'), [
        'name' => 'Should Fail',
    ])->assertForbidden();

    $this->actingAs($user)->put(route('departments.update', $this->department), [
        'name' => 'Should Fail Update',
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('departments.destroy', $this->department))->assertForbidden();
});

test('roles view-only users can open roles page but cannot mutate roles', function () {
    $user = userWithPermissions(
        ['admin-dashboard', 'roles.view'],
        $this->org,
        $this->plant,
    );

    $role = Role::create([
        'name' => 'Mutable Role',
        'slug' => 'mutable-role-'.uniqid(),
        'description' => 'For mutation denial',
    ]);

    $this->actingAs($user)->get(route('admin.roles.index'))->assertOk();

    $this->actingAs($user)->post(route('admin.roles.store'), [
        'name' => 'Unauthorized Role',
        'description' => 'Nope',
        'permissions' => [],
    ])->assertForbidden();

    $this->actingAs($user)->put(route('admin.roles.update', $role), [
        'name' => 'Unauthorized Update',
        'description' => 'Nope',
        'permissions' => [],
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('admin.roles.destroy', $role))->assertForbidden();
});

test('plants view-only users cannot create update or delete plants', function () {
    $user = userWithPermissions(
        ['admin-dashboard', 'plants.view'],
        $this->org,
        $this->plant,
    );

    $secondPlant = Plant::create([
        'organization_id' => $this->org->id,
        'name' => 'Second Plant',
        'code' => 'PB2',
        'slug' => 'permission-boundary-plant-2',
        'is_default' => false,
    ]);

    $this->actingAs($user)->post(route('plants.store'), [
        'name' => 'Unauthorized Plant',
        'code' => 'PBX',
        'slug' => 'unauthorized-plant',
        'status' => 'Active',
        'is_default' => false,
    ])->assertForbidden();

    $this->actingAs($user)->put(route('plants.update', $this->plant), [
        'name' => 'Unauthorized Update',
        'code' => 'PB1',
        'slug' => 'permission-boundary-plant',
        'status' => 'Active',
        'is_default' => true,
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('plants.destroy', $secondPlant))->assertForbidden();
});
