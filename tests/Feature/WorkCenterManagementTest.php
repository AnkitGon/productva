<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkCenter;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Work Center Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'WC1',
        'slug' => 'work-center-test-plant',
    ], [
        'name' => 'Work Center Test Plant',
        'is_default' => true,
    ]);

    $this->otherPlant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'WC2',
        'slug' => 'work-center-test-plant-2',
    ], [
        'name' => 'Work Center Test Plant 2',
        'is_default' => false,
    ]);

    $this->department = Department::factory()->create([
        'name' => 'Production',
        'code' => 'PROD',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->otherDepartment = Department::factory()->create([
        'name' => 'Other Production',
        'code' => 'PROD',
        'organization_id' => $this->org->id,
        'plant_id' => $this->otherPlant->id,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);
});

function createWorkCenter(User $user, Plant $plant, Department $department, array $overrides = []): WorkCenter
{
    return WorkCenter::create(array_merge([
        'organization_id' => $user->organization_id,
        'plant_id' => $plant->id,
        'department_id' => $department->id,
        'code' => 'CNC',
        'name' => 'CNC Machining',
        'status' => 'Active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

test('admin can view work centers for the active plant only', function () {
    createWorkCenter($this->admin, $this->plant, $this->department);
    createWorkCenter($this->admin, $this->otherPlant, $this->otherDepartment, [
        'code' => 'CNC',
        'name' => 'Other Plant CNC',
    ]);

    $this->actingAs($this->admin)
        ->get(route('work-centers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-centers/index')
            ->has('workCenters.data', 1)
            ->where('workCenters.data.0.code', 'CNC')
            ->has('departments')
        );
});

test('admin can create a work center on the active plant', function () {
    $supervisor = Employee::create([
        'employee_code' => 'SUP-01',
        'first_name' => 'Sue',
        'last_name' => 'Pervisor',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $this->actingAs($this->admin)
        ->post(route('work-centers.store'), [
            'department_id' => $this->department->id,
            'code' => 'WELD',
            'name' => 'Welding Bay',
            'description' => 'Main welding area',
            'supervisor_employee_id' => $supervisor->id,
            'capacity' => 12.5,
            'capacity_uom' => 'jobs/hour',
            'status' => 'Active',
        ])
        ->assertRedirect();

    $workCenter = WorkCenter::where('code', 'WELD')->first();
    expect($workCenter)->not->toBeNull();
    expect($workCenter->plant_id)->toBe($this->plant->id);
    expect($workCenter->supervisor_employee_id)->toBe($supervisor->id);
    expect($workCenter->canReceiveProductionOrders())->toBeTrue();
});

test('work center code must be unique per plant', function () {
    createWorkCenter($this->admin, $this->plant, $this->department, ['code' => 'CNC']);

    $this->actingAs($this->admin)
        ->post(route('work-centers.store'), [
            'department_id' => $this->department->id,
            'code' => 'CNC',
            'name' => 'Duplicate CNC',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');

    $this->admin->update(['active_plant_id' => $this->otherPlant->id]);

    $this->actingAs($this->admin)
        ->post(route('work-centers.store'), [
            'department_id' => $this->otherDepartment->id,
            'code' => 'CNC',
            'name' => 'CNC Other Plant',
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(WorkCenter::where('code', 'CNC')->count())->toBe(2);
});

test('department must belong to the active plant', function () {
    $this->actingAs($this->admin)
        ->post(route('work-centers.store'), [
            'department_id' => $this->otherDepartment->id,
            'code' => 'PAINT',
            'name' => 'Painting',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('department_id');
});

test('supervisor must belong to the active plant', function () {
    $otherSupervisor = Employee::create([
        'employee_code' => 'SUP-02',
        'first_name' => 'Other',
        'last_name' => 'Plant',
        'organization_id' => $this->org->id,
        'plant_id' => $this->otherPlant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $this->actingAs($this->admin)
        ->post(route('work-centers.store'), [
            'department_id' => $this->department->id,
            'code' => 'ASSY',
            'name' => 'Assembly',
            'supervisor_employee_id' => $otherSupervisor->id,
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('supervisor_employee_id');
});

test('inactive work centers cannot receive production orders', function () {
    $workCenter = createWorkCenter($this->admin, $this->plant, $this->department, [
        'code' => 'INACT',
        'status' => 'Inactive',
    ]);

    expect($workCenter->canReceiveProductionOrders())->toBeFalse();
});

test('view-only users cannot mutate work centers', function () {
    $permission = Permission::where('slug', 'work-centers.view')->firstOrFail();
    $role = Role::create([
        'name' => 'Work Center Viewer',
        'slug' => 'work-center-viewer-'.uniqid(),
        'description' => 'View only',
    ]);
    $role->permissions()->sync([
        $permission->id,
        Permission::where('slug', 'admin-dashboard')->value('id'),
    ]);

    $viewer = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $viewer->roles()->attach($role);

    $workCenter = createWorkCenter($this->admin, $this->plant, $this->department);

    $this->actingAs($viewer)->get(route('work-centers.index'))->assertOk();
    $this->actingAs($viewer)->post(route('work-centers.store'), [
        'department_id' => $this->department->id,
        'code' => 'X',
        'name' => 'X',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->put(route('work-centers.update', $workCenter), [
        'department_id' => $this->department->id,
        'code' => 'CNC',
        'name' => 'Updated',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->delete(route('work-centers.destroy', $workCenter))->assertForbidden();
});
