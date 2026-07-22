<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Warehouse Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'WH1',
        'slug' => 'warehouse-test-plant',
    ], [
        'name' => 'Warehouse Test Plant',
        'is_default' => true,
    ]);
    $this->otherPlant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'WH2',
        'slug' => 'warehouse-test-plant-2',
    ], [
        'name' => 'Warehouse Test Plant 2',
        'is_default' => false,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    WarehouseType::ensureDefaultsFor($this->org->id);
    $this->rawType = WarehouseType::where('organization_id', $this->org->id)->where('code', 'RAW')->firstOrFail();
    $this->fgType = WarehouseType::where('organization_id', $this->org->id)->where('code', 'FG')->firstOrFail();
    $this->inactiveType = WarehouseType::create([
        'organization_id' => $this->org->id,
        'code' => 'OLD',
        'name' => 'Inactive Type',
        'status' => 'Inactive',
    ]);
});

function createWarehouse(User $user, Plant $plant, WarehouseType $type, array $overrides = []): Warehouse
{
    return Warehouse::create(array_merge([
        'organization_id' => $user->organization_id,
        'plant_id' => $plant->id,
        'warehouse_type_id' => $type->id,
        'code' => 'RM001',
        'name' => 'Raw Material Warehouse',
        'status' => 'Active',
        'allow_negative_stock' => false,
        'is_default' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

test('admin can view warehouses for the active plant only', function () {
    createWarehouse($this->admin, $this->plant, $this->rawType);
    createWarehouse($this->admin, $this->otherPlant, $this->rawType, [
        'code' => 'RM001',
        'name' => 'Other Plant WH',
    ]);

    $this->actingAs($this->admin)
        ->get(route('warehouses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('warehouses/index')
            ->has('warehouses.data', 1)
            ->where('warehouses.data.0.code', 'RM001')
            ->has('warehouseTypes')
        );
});

test('admin can create a warehouse on the active plant', function () {
    $manager = Employee::create([
        'employee_code' => 'MGR-01',
        'first_name' => 'Max',
        'last_name' => 'Manager',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), [
            'warehouse_type_id' => $this->fgType->id,
            'code' => 'fg-01',
            'name' => 'Finished Goods',
            'manager_employee_id' => $manager->id,
            'email' => 'wh@example.com',
            'phone' => '1234567890',
            'allow_negative_stock' => false,
            'is_default' => true,
            'status' => 'Active',
        ])
        ->assertRedirect();

    $warehouse = Warehouse::where('code', 'FG-01')->first();
    expect($warehouse)->not->toBeNull();
    expect($warehouse->plant_id)->toBe($this->plant->id);
    expect($warehouse->is_default)->toBeTrue();
    expect($warehouse->manager_employee_id)->toBe($manager->id);
});

test('warehouse code must be unique within plant', function () {
    createWarehouse($this->admin, $this->plant, $this->rawType, ['code' => 'RM001']);

    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), [
            'warehouse_type_id' => $this->rawType->id,
            'code' => 'RM001',
            'name' => 'Duplicate',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');

    $this->admin->update(['active_plant_id' => $this->otherPlant->id]);

    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), [
            'warehouse_type_id' => $this->rawType->id,
            'code' => 'RM001',
            'name' => 'Other Plant RM',
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(Warehouse::where('code', 'RM001')->count())->toBe(2);
});

test('only one default warehouse is allowed per plant', function () {
    $first = createWarehouse($this->admin, $this->plant, $this->rawType, [
        'code' => 'A',
        'is_default' => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), [
            'warehouse_type_id' => $this->fgType->id,
            'code' => 'B',
            'name' => 'Second Default',
            'is_default' => true,
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse();
    expect(Warehouse::where('plant_id', $this->plant->id)->where('is_default', true)->count())->toBe(1);
    expect(Warehouse::where('code', 'B')->first()->is_default)->toBeTrue();
});

test('warehouse type must be active', function () {
    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), [
            'warehouse_type_id' => $this->inactiveType->id,
            'code' => 'BAD',
            'name' => 'Bad Type',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('warehouse_type_id');
});

test('manager must belong to the active plant', function () {
    $otherManager = Employee::create([
        'employee_code' => 'MGR-02',
        'first_name' => 'Other',
        'last_name' => 'Plant',
        'organization_id' => $this->org->id,
        'plant_id' => $this->otherPlant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), [
            'warehouse_type_id' => $this->rawType->id,
            'code' => 'MGR',
            'name' => 'Managed WH',
            'manager_employee_id' => $otherManager->id,
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('manager_employee_id');
});

test('email must be valid when provided', function () {
    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), [
            'warehouse_type_id' => $this->rawType->id,
            'code' => 'EM',
            'name' => 'Email WH',
            'email' => 'not-an-email',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('email');
});

test('admin can archive a warehouse without blocking dependencies', function () {
    $warehouse = createWarehouse($this->admin, $this->plant, $this->rawType);

    $this->actingAs($this->admin)
        ->delete(route('warehouses.destroy', $warehouse))
        ->assertRedirect();

    expect(Warehouse::find($warehouse->id))->toBeNull();
    expect(Warehouse::withTrashed()->find($warehouse->id))->not->toBeNull();
});

test('warehouses can be searched and filtered', function () {
    createWarehouse($this->admin, $this->plant, $this->rawType, [
        'code' => 'RAW-1',
        'name' => 'Raw Store',
        'email' => 'raw@example.com',
    ]);
    createWarehouse($this->admin, $this->plant, $this->fgType, [
        'code' => 'FG-1',
        'name' => 'Finished Store',
        'status' => 'Inactive',
    ]);

    $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['search' => 'raw@example.com']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('warehouses.data', 1)->where('warehouses.data.0.code', 'RAW-1'));

    $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['warehouse_type_id' => $this->fgType->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('warehouses.data', 1)->where('warehouses.data.0.code', 'FG-1'));

    $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['status' => 'Inactive']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('warehouses.data', 1)->where('warehouses.data.0.code', 'FG-1'));
});

test('view-only users cannot mutate warehouses', function () {
    $permission = Permission::where('slug', 'warehouses.view')->firstOrFail();
    $role = Role::create([
        'name' => 'Warehouse Viewer',
        'slug' => 'warehouse-viewer-'.uniqid(),
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

    $warehouse = createWarehouse($this->admin, $this->plant, $this->rawType);

    $this->actingAs($viewer)->get(route('warehouses.index'))->assertOk();
    $this->actingAs($viewer)->post(route('warehouses.store'), [
        'warehouse_type_id' => $this->rawType->id,
        'code' => 'X',
        'name' => 'X',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->put(route('warehouses.update', $warehouse), [
        'warehouse_type_id' => $this->rawType->id,
        'code' => 'RM001',
        'name' => 'Updated',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->delete(route('warehouses.destroy', $warehouse))->assertForbidden();
});
