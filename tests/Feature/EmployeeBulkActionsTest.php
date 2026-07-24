<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Bulk Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'BP1',
        'slug' => 'bulk-plant',
    ], [
        'name' => 'Bulk Plant',
    ]);

    $adminRole = Role::where('slug', 'admin')->first();
    $this->adminUser = User::create([
        'name' => 'Bulk Admin',
        'email' => 'bulk-admin@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->adminUser->roles()->sync([$adminRole->id]);

    $this->department = Department::factory()->create([
        'name' => 'Ops',
        'code' => 'OPS',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'status' => 'Active',
    ]);

    $this->shift = Shift::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'code' => 'DAY',
        'name' => 'Day Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'break_minutes' => 30,
        'overnight' => false,
        'working_minutes' => 450,
        'status' => 'Active',
        'created_by' => $this->adminUser->id,
    ]);
});

function makeBulkEmployee(array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'employee_code' => 'BLK-'.uniqid(),
        'first_name' => 'Bulk',
        'last_name' => 'Worker',
        'organization_id' => test()->org->id,
        'plant_id' => test()->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Inactive',
    ], $overrides));
}

it('can activate selected employees', function () {
    $one = makeBulkEmployee(['status' => 'Inactive']);
    $two = makeBulkEmployee(['status' => 'Inactive', 'employee_code' => 'BLK-2']);

    $this->actingAs($this->adminUser)->post(route('employees.bulk'), [
        'action' => 'activate',
        'ids' => [$one->id, $two->id],
    ])->assertRedirect();

    expect($one->fresh()->status)->toBe('Active')
        ->and($two->fresh()->status)->toBe('Active');
});

it('can deactivate selected employees', function () {
    $one = makeBulkEmployee(['status' => 'Active']);

    $this->actingAs($this->adminUser)->post(route('employees.bulk'), [
        'action' => 'deactivate',
        'ids' => [$one->id],
    ])->assertRedirect();

    expect($one->fresh()->status)->toBe('Inactive');
});

it('can assign a shift to selected employees', function () {
    $one = makeBulkEmployee();

    $this->actingAs($this->adminUser)->post(route('employees.bulk'), [
        'action' => 'assign_shift',
        'ids' => [$one->id],
        'shift_id' => $this->shift->id,
    ])->assertRedirect();

    expect($one->fresh()->shift_id)->toBe($this->shift->id);
});

it('can assign a department to selected employees', function () {
    $one = makeBulkEmployee();

    $this->actingAs($this->adminUser)->post(route('employees.bulk'), [
        'action' => 'assign_department',
        'ids' => [$one->id],
        'department_id' => $this->department->id,
    ])->assertRedirect();

    expect($one->fresh()->department_id)->toBe($this->department->id);
});

it('can export selected employees as csv', function () {
    $one = makeBulkEmployee([
        'employee_code' => 'BLK-CSV',
        'first_name' => 'Export',
        'last_name' => 'Me',
        'status' => 'Active',
    ]);

    $response = $this->actingAs($this->adminUser)->post(route('employees.bulk'), [
        'action' => 'export',
        'ids' => [$one->id],
    ]);

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('BLK-CSV')
        ->and($response->streamedContent())->toContain('Export');
});

it('can delete selected employees', function () {
    $one = makeBulkEmployee(['employee_code' => 'BLK-DEL']);

    $this->actingAs($this->adminUser)->post(route('employees.bulk'), [
        'action' => 'delete',
        'ids' => [$one->id],
    ])->assertRedirect();

    expect(Employee::find($one->id))->toBeNull()
        ->and(Employee::withTrashed()->find($one->id))->not->toBeNull();
});

it('rejects bulk update without employees.update permission', function () {
    $viewPermission = Permission::where('slug', 'employees.view')->firstOrFail();

    $viewerRole = Role::create([
        'name' => 'Employee Viewer',
        'slug' => 'employee-viewer-bulk',
    ]);
    $viewerRole->permissions()->sync([$viewPermission->id]);

    $viewer = User::create([
        'name' => 'Viewer',
        'email' => 'bulk-viewer@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $viewer->roles()->sync([$viewerRole->id]);

    $one = makeBulkEmployee();

    $this->actingAs($viewer)->post(route('employees.bulk'), [
        'action' => 'activate',
        'ids' => [$one->id],
    ])->assertForbidden();
});
