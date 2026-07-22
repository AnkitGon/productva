<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WarehouseType;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Warehouse Type Test Org']);
    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);
});

test('warehouse types index seeds defaults for the organization', function () {
    $this->actingAs($this->admin)
        ->get(route('warehouse-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('warehouse-types/index')
            ->has('warehouseTypes.data', count(WarehouseType::DEFAULTS))
        );

    expect(WarehouseType::where('organization_id', $this->org->id)->where('code', 'RAW')->exists())->toBeTrue();
    expect(WarehouseType::where('organization_id', $this->org->id)->where('code', 'FG')->exists())->toBeTrue();
});

test('admin can create a custom warehouse type', function () {
    WarehouseType::ensureDefaultsFor($this->org->id);

    $this->actingAs($this->admin)
        ->post(route('warehouse-types.store'), [
            'code' => 'tool',
            'name' => 'Tooling',
            'description' => 'Tool crib',
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(WarehouseType::where('code', 'TOOL')->where('organization_id', $this->org->id)->exists())->toBeTrue();
});

test('warehouse type code must be unique within organization', function () {
    WarehouseType::ensureDefaultsFor($this->org->id);

    $this->actingAs($this->admin)
        ->post(route('warehouse-types.store'), [
            'code' => 'RAW',
            'name' => 'Duplicate',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');
});

test('view-only users cannot mutate warehouse types', function () {
    $permission = Permission::where('slug', 'warehouses.view')->firstOrFail();
    $role = Role::create([
        'name' => 'WH Type Viewer',
        'slug' => 'wh-type-viewer-'.uniqid(),
        'description' => 'View only',
    ]);
    $role->permissions()->sync([
        $permission->id,
        Permission::where('slug', 'admin-dashboard')->value('id'),
    ]);

    $viewer = User::factory()->create(['organization_id' => $this->org->id]);
    $viewer->roles()->attach($role);

    WarehouseType::ensureDefaultsFor($this->org->id);
    $type = WarehouseType::where('organization_id', $this->org->id)->first();

    $this->actingAs($viewer)->get(route('warehouse-types.index'))->assertOk();
    $this->actingAs($viewer)->post(route('warehouse-types.store'), [
        'code' => 'X',
        'name' => 'X',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->put(route('warehouse-types.update', $type), [
        'code' => $type->code,
        'name' => 'Updated',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->delete(route('warehouse-types.destroy', $type))->assertForbidden();
});
