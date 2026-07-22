<?php

use App\Models\Department;
use App\Models\Machine;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkCenter;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Machine Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'MC1',
        'slug' => 'machine-test-plant',
    ], [
        'name' => 'Machine Test Plant',
        'is_default' => true,
    ]);

    $this->otherPlant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'MC2',
        'slug' => 'machine-test-plant-2',
    ], [
        'name' => 'Machine Test Plant 2',
        'is_default' => false,
    ]);

    $this->department = Department::create([
        'name' => 'Machining',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->otherDepartment = Department::create([
        'name' => 'Other Machining',
        'organization_id' => $this->org->id,
        'plant_id' => $this->otherPlant->id,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->workCenter = WorkCenter::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'department_id' => $this->department->id,
        'code' => 'CNC',
        'name' => 'CNC Cell',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->otherWorkCenter = WorkCenter::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->otherPlant->id,
        'department_id' => $this->otherDepartment->id,
        'code' => 'CNC',
        'name' => 'Other CNC Cell',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);
});

function createMachine(User $user, Plant $plant, Department $department, WorkCenter $workCenter, array $overrides = []): Machine
{
    return Machine::create(array_merge([
        'organization_id' => $user->organization_id,
        'plant_id' => $plant->id,
        'department_id' => $department->id,
        'work_center_id' => $workCenter->id,
        'code' => 'MCH-01',
        'name' => 'Haas VF-2',
        'status' => 'Active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

test('admin can view machines for the active plant only', function () {
    createMachine($this->admin, $this->plant, $this->department, $this->workCenter);
    createMachine($this->admin, $this->otherPlant, $this->otherDepartment, $this->otherWorkCenter, [
        'code' => 'MCH-01',
        'name' => 'Other Plant Machine',
    ]);

    $this->actingAs($this->admin)
        ->get(route('machines.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('machines/index')
            ->has('machines.data', 1)
            ->where('machines.data.0.code', 'MCH-01')
            ->has('departments')
            ->has('workCenters')
            ->has('statuses')
        );
});

test('admin can create a machine on the active plant', function () {
    $this->actingAs($this->admin)
        ->post(route('machines.store'), [
            'department_id' => $this->department->id,
            'work_center_id' => $this->workCenter->id,
            'code' => 'lathe-1',
            'name' => 'Haas ST-20',
            'manufacturer' => 'Haas',
            'model' => 'ST-20',
            'serial_number' => 'SN-1001',
            'asset_tag' => 'AT-1001',
            'purchase_date' => '2024-01-15',
            'installation_date' => '2024-02-01',
            'capacity' => 40,
            'capacity_unit' => 'pcs/hr',
            'status' => 'Active',
            'notes' => 'Primary lathe',
        ])
        ->assertRedirect();

    $machine = Machine::where('code', 'LATHE-1')->first();
    expect($machine)->not->toBeNull();
    expect($machine->plant_id)->toBe($this->plant->id);
    expect($machine->department_id)->toBe($this->department->id);
    expect($machine->work_center_id)->toBe($this->workCenter->id);
    expect($machine->manufacturer)->toBe('Haas');
    expect($machine->canBeAssignedToProductionOrders())->toBeTrue();
});

test('admin can update a machine', function () {
    $machine = createMachine($this->admin, $this->plant, $this->department, $this->workCenter);

    $this->actingAs($this->admin)
        ->put(route('machines.update', $machine), [
            'department_id' => $this->department->id,
            'work_center_id' => $this->workCenter->id,
            'code' => 'MCH-01',
            'name' => 'Updated VF-2',
            'manufacturer' => 'Haas',
            'model' => 'VF-2SS',
            'status' => 'Idle',
            'notes' => 'Updated notes',
        ])
        ->assertRedirect();

    $machine->refresh();
    expect($machine->name)->toBe('Updated VF-2');
    expect($machine->status)->toBe('Idle');
    expect($machine->model)->toBe('VF-2SS');
});

test('admin can archive a machine without production history', function () {
    $machine = createMachine($this->admin, $this->plant, $this->department, $this->workCenter);

    $this->actingAs($this->admin)
        ->delete(route('machines.destroy', $machine))
        ->assertRedirect();

    expect(Machine::find($machine->id))->toBeNull();
    expect(Machine::withTrashed()->find($machine->id))->not->toBeNull();
});

test('machine code must be unique per plant', function () {
    createMachine($this->admin, $this->plant, $this->department, $this->workCenter, ['code' => 'MCH-01']);

    $this->actingAs($this->admin)
        ->post(route('machines.store'), [
            'department_id' => $this->department->id,
            'work_center_id' => $this->workCenter->id,
            'code' => 'MCH-01',
            'name' => 'Duplicate',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');

    $this->admin->update(['active_plant_id' => $this->otherPlant->id]);

    $this->actingAs($this->admin)
        ->post(route('machines.store'), [
            'department_id' => $this->otherDepartment->id,
            'work_center_id' => $this->otherWorkCenter->id,
            'code' => 'MCH-01',
            'name' => 'Other Plant Machine',
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(Machine::where('code', 'MCH-01')->count())->toBe(2);
});

test('serial number must be unique within the organization when provided', function () {
    createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'MCH-01',
        'serial_number' => 'SN-UNIQUE',
    ]);

    $this->actingAs($this->admin)
        ->post(route('machines.store'), [
            'department_id' => $this->department->id,
            'work_center_id' => $this->workCenter->id,
            'code' => 'MCH-02',
            'name' => 'Second Machine',
            'serial_number' => 'SN-UNIQUE',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('serial_number');
});

test('work center must belong to the selected department and active plant', function () {
    $wrongDepartment = Department::create([
        'name' => 'Assembly',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('machines.store'), [
            'department_id' => $wrongDepartment->id,
            'work_center_id' => $this->workCenter->id,
            'code' => 'BAD-WC',
            'name' => 'Bad Work Center',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('work_center_id');

    $this->actingAs($this->admin)
        ->post(route('machines.store'), [
            'department_id' => $this->otherDepartment->id,
            'work_center_id' => $this->otherWorkCenter->id,
            'code' => 'BAD-PLANT',
            'name' => 'Wrong Plant',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors(['department_id', 'work_center_id']);
});

test('machines can be filtered by search status and manufacturer', function () {
    createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'HAAS-1',
        'name' => 'Haas Mill',
        'manufacturer' => 'Haas',
        'model' => 'VF-2',
        'status' => 'Active',
        'asset_tag' => 'AT-HAAS',
        'serial_number' => 'SN-HAAS',
    ]);
    createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'FANUC-1',
        'name' => 'Fanuc Robot',
        'manufacturer' => 'Fanuc',
        'status' => 'Maintenance',
    ]);

    $this->actingAs($this->admin)
        ->get(route('machines.index', ['search' => 'AT-HAAS']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('machines.data', 1)->where('machines.data.0.code', 'HAAS-1'));

    $this->actingAs($this->admin)
        ->get(route('machines.index', ['status' => 'Maintenance']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('machines.data', 1)->where('machines.data.0.code', 'FANUC-1'));

    $this->actingAs($this->admin)
        ->get(route('machines.index', ['manufacturer' => 'Haas']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('machines.data', 1)->where('machines.data.0.code', 'HAAS-1'));
});

test('non-assignable machine statuses cannot be used for production orders', function () {
    $active = createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'ACT',
        'status' => 'Active',
    ]);
    $idle = createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'IDL',
        'status' => 'Idle',
    ]);
    $running = createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'RUN',
        'status' => 'Running',
    ]);
    $maintenance = createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'MNT',
        'status' => 'Maintenance',
    ]);
    $breakdown = createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'BRK',
        'status' => 'Breakdown',
    ]);
    $retired = createMachine($this->admin, $this->plant, $this->department, $this->workCenter, [
        'code' => 'RET',
        'status' => 'Retired',
    ]);

    expect($active->canBeAssignedToProductionOrders())->toBeTrue();
    expect($idle->canBeAssignedToProductionOrders())->toBeTrue();
    expect($running->canBeAssignedToProductionOrders())->toBeTrue();
    expect($maintenance->canBeAssignedToProductionOrders())->toBeFalse();
    expect($breakdown->canBeAssignedToProductionOrders())->toBeFalse();
    expect($retired->canBeAssignedToProductionOrders())->toBeFalse();
});

test('work center cannot be archived while machines are assigned', function () {
    createMachine($this->admin, $this->plant, $this->department, $this->workCenter);

    expect($this->workCenter->hasAssignedMachines())->toBeTrue();

    $this->actingAs($this->admin)
        ->delete(route('work-centers.destroy', $this->workCenter))
        ->assertRedirect();

    expect(WorkCenter::find($this->workCenter->id))->not->toBeNull();
});

test('view-only users cannot mutate machines', function () {
    $permission = Permission::where('slug', 'machine.view')->firstOrFail();
    $role = Role::create([
        'name' => 'Machine Viewer',
        'slug' => 'machine-viewer-'.uniqid(),
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

    $machine = createMachine($this->admin, $this->plant, $this->department, $this->workCenter);

    $this->actingAs($viewer)->get(route('machines.index'))->assertOk();
    $this->actingAs($viewer)->post(route('machines.store'), [
        'department_id' => $this->department->id,
        'work_center_id' => $this->workCenter->id,
        'code' => 'X',
        'name' => 'X',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->put(route('machines.update', $machine), [
        'department_id' => $this->department->id,
        'work_center_id' => $this->workCenter->id,
        'code' => 'MCH-01',
        'name' => 'Updated',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->delete(route('machines.destroy', $machine))->assertForbidden();
});
