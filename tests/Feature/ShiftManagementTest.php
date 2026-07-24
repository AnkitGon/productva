<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Shift Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'SH1',
        'slug' => 'shift-test-plant',
    ], [
        'name' => 'Shift Test Plant',
        'is_default' => true,
    ]);

    $this->otherPlant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'SH2',
        'slug' => 'shift-test-plant-2',
    ], [
        'name' => 'Shift Test Plant 2',
        'is_default' => false,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);
});

function createShift(User $user, Plant $plant, array $overrides = []): Shift
{
    $start = $overrides['start_time'] ?? '06:00';
    $end = $overrides['end_time'] ?? '14:00';
    $overnight = $overrides['overnight'] ?? false;
    $break = $overrides['break_minutes'] ?? 60;

    return Shift::create(array_merge([
        'organization_id' => $user->organization_id,
        'plant_id' => $plant->id,
        'code' => 'MORN',
        'name' => 'Morning Shift',
        'start_time' => $start,
        'end_time' => $end,
        'break_minutes' => $break,
        'grace_in_minutes' => 10,
        'grace_out_minutes' => 5,
        'overnight' => $overnight,
        'working_minutes' => Shift::calculateWorkingMinutes($start, $end, (bool) $overnight, (int) $break),
        'status' => 'Active',
        'color' => 'blue',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

test('admin can view shifts for the active plant only', function () {
    createShift($this->admin, $this->plant);
    createShift($this->admin, $this->otherPlant, ['code' => 'MORN']);

    $this->actingAs($this->admin)
        ->get(route('shifts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('shifts/index')
            ->has('shifts.data', 1)
            ->where('shifts.data.0.code', 'MORN')
            ->has('templates')
            ->has('colors')
            ->missing('plants')
        );
});

test('admin can create a morning shift with calculated working minutes', function () {
    $this->actingAs($this->admin)
        ->post(route('shifts.store'), [
            'code' => 'MORN',
            'name' => 'Morning Shift',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'overnight' => false,
            'break_minutes' => 60,
            'grace_in_minutes' => 10,
            'grace_out_minutes' => 5,
            'status' => 'Active',
            'color' => 'blue',
            'notes' => 'Standard day shift',
        ])
        ->assertRedirect();

    $shift = Shift::where('code', 'MORN')->where('plant_id', $this->plant->id)->first();
    expect($shift)->not->toBeNull();
    expect($shift->working_minutes)->toBe(420);
    expect($shift->hours_label)->toBe('7h');
    expect($shift->plant_id)->toBe($this->plant->id);
    expect($shift->color)->toBe('blue');
});

test('overnight night shift calculates duration across midnight', function () {
    $this->actingAs($this->admin)
        ->post(route('shifts.store'), [
            'code' => 'NIGHT',
            'name' => 'Night Shift',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'overnight' => true,
            'break_minutes' => 60,
            'grace_in_minutes' => 0,
            'grace_out_minutes' => 0,
            'status' => 'Active',
            'color' => 'purple',
        ])
        ->assertRedirect();

    $shift = Shift::where('code', 'NIGHT')->firstOrFail();
    expect($shift->working_minutes)->toBe(420);
    expect($shift->color)->toBe('purple');
});

test('shift code must be unique per plant but can repeat across plants', function () {
    createShift($this->admin, $this->plant, ['code' => 'MORN']);

    $this->actingAs($this->admin)
        ->post(route('shifts.store'), [
            'code' => 'MORN',
            'name' => 'Duplicate Morning',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'overnight' => false,
            'break_minutes' => 0,
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');

    $this->admin->update(['active_plant_id' => $this->otherPlant->id]);

    $this->actingAs($this->admin)
        ->post(route('shifts.store'), [
            'code' => 'MORN',
            'name' => 'Morning Other Plant',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'overnight' => false,
            'break_minutes' => 0,
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(Shift::where('code', 'MORN')->count())->toBe(2);
});

test('end time cannot equal start time', function () {
    $this->actingAs($this->admin)
        ->post(route('shifts.store'), [
            'code' => 'BAD',
            'name' => 'Bad Shift',
            'start_time' => '08:00',
            'end_time' => '08:00',
            'overnight' => false,
            'break_minutes' => 0,
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('end_time');
});

test('break cannot exceed shift duration', function () {
    $this->actingAs($this->admin)
        ->post(route('shifts.store'), [
            'code' => 'LONG',
            'name' => 'Too Much Break',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'overnight' => false,
            'break_minutes' => 500,
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('break_minutes');
});

test('shift notes cannot exceed 1000 characters', function () {
    $this->actingAs($this->admin)
        ->post(route('shifts.store'), [
            'code' => 'LONGNOTE',
            'name' => 'Long Notes',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'overnight' => false,
            'break_minutes' => 0,
            'status' => 'Active',
            'color' => 'blue',
            'notes' => str_repeat('a', 1001),
        ])
        ->assertSessionHasErrors('notes');
});

test('employees index can be filtered by shift', function () {
    $shift = createShift($this->admin, $this->plant, ['code' => 'MORN', 'color' => 'blue']);
    $otherShift = createShift($this->admin, $this->plant, ['code' => 'EVE', 'name' => 'Evening', 'color' => 'orange']);

    Employee::create([
        'employee_code' => 'SH-VIEW-1',
        'first_name' => 'On',
        'last_name' => 'Morning',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'shift_id' => $shift->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    Employee::create([
        'employee_code' => 'SH-VIEW-2',
        'first_name' => 'On',
        'last_name' => 'Evening',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'shift_id' => $otherShift->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $this->actingAs($this->admin)
        ->get(route('employees.index', ['shift_id' => $shift->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.employee_code', 'SH-VIEW-1')
            ->where('filters.shift_id', (string) $shift->id)
        );
});

test('cannot archive shift with assigned employees', function () {
    $shift = createShift($this->admin, $this->plant);

    Employee::create([
        'employee_code' => 'SH-EMP-1',
        'first_name' => 'Shift',
        'last_name' => 'Worker',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'shift_id' => $shift->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('shifts.destroy', $shift))
        ->assertRedirect();

    expect(Shift::find($shift->id))->not->toBeNull();
});

test('inactive shifts cannot be assigned to new employees', function () {
    $shift = createShift($this->admin, $this->plant, [
        'code' => 'INACT',
        'name' => 'Inactive Shift',
        'status' => 'Inactive',
    ]);

    $this->actingAs($this->admin)
        ->post(route('employees.store'), [
            'employee_code' => 'SH-EMP-2',
            'first_name' => 'No',
            'last_name' => 'Assign',
            'shift_id' => $shift->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
            'create_login' => false,
        ])
        ->assertSessionHasErrors('shift_id');
});

test('view-only shift users cannot mutate shifts', function () {
    $permission = Permission::where('slug', 'shift.view')->firstOrFail();
    $role = Role::create([
        'name' => 'Shift Viewer',
        'slug' => 'shift-viewer-'.uniqid(),
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

    $shift = createShift($this->admin, $this->plant);

    $this->actingAs($viewer)->get(route('shifts.index'))->assertOk();
    $this->actingAs($viewer)->post(route('shifts.store'), [
        'code' => 'X',
        'name' => 'X',
        'start_time' => '06:00',
        'end_time' => '14:00',
        'overnight' => false,
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->put(route('shifts.update', $shift), [
        'code' => 'MORN',
        'name' => 'Updated',
        'start_time' => '06:00',
        'end_time' => '14:00',
        'overnight' => false,
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->delete(route('shifts.destroy', $shift))->assertForbidden();
});
