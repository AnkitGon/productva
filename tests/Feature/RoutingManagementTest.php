<?php

use App\Models\Department;
use App\Models\Machine;
use App\Models\Operation;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\RoutingHeader;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkCenter;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Routing Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'RT1',
        'slug' => 'routing-test-plant',
    ], [
        'name' => 'Routing Test Plant',
        'is_default' => true,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->department = Department::factory()->create([
        'name' => 'Production',
        'code' => 'PROD',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->workCenter = WorkCenter::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'department_id' => $this->department->id,
        'code' => 'WC-CUT',
        'name' => 'Cutting Center',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->assemblyWc = WorkCenter::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'department_id' => $this->department->id,
        'code' => 'WC-ASM',
        'name' => 'Assembly Center',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->machine = Machine::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'department_id' => $this->department->id,
        'work_center_id' => $this->workCenter->id,
        'code' => 'SAW-01',
        'name' => 'Panel Saw',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->category = ProductCategory::create([
        'organization_id' => $this->org->id,
        'code' => 'FG',
        'name' => 'Finished Goods',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->uom = UnitOfMeasure::create([
        'organization_id' => $this->org->id,
        'code' => 'PCS',
        'name' => 'Pieces',
        'symbol' => 'pcs',
        'type' => 'Count',
        'decimal_places' => 0,
        'status' => 'Active',
    ]);

    $this->chair = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'CHAIR-RT',
        'name' => 'Wooden Chair',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Finished Good',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->cut = Operation::create([
        'organization_id' => $this->org->id,
        'code' => 'CUT',
        'name' => 'Cutting',
        'type' => 'Manufacturing',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->assemble = Operation::create([
        'organization_id' => $this->org->id,
        'code' => 'ASM',
        'name' => 'Assembly',
        'type' => 'Manufacturing',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);
});

function validRoutingPayload($test, array $overrides = []): array
{
    return array_merge([
        'product_id' => $test->chair->id,
        'version' => '1.0',
        'is_default' => true,
        'effective_from' => '2026-07-01',
        'status' => 'Draft',
        'notes' => 'Chair routing',
        'operations' => [
            [
                'sequence' => 10,
                'operation_id' => $test->cut->id,
                'work_center_id' => $test->workCenter->id,
                'machine_id' => $test->machine->id,
                'setup_time_minutes' => 10,
                'run_time_per_unit' => 2,
            ],
            [
                'sequence' => 20,
                'operation_id' => $test->assemble->id,
                'work_center_id' => $test->assemblyWc->id,
                'machine_id' => null,
                'setup_time_minutes' => 5,
                'run_time_per_unit' => 8,
            ],
        ],
    ], $overrides);
}

test('admin can view routings index', function () {
    $this->actingAs($this->admin)
        ->get(route('routings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('routings/index')->has('routings'));
});

test('admin can create a routing with operations', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this))
        ->assertRedirect();

    $routing = RoutingHeader::where('product_id', $this->chair->id)->first();
    expect($routing)->not->toBeNull();
    expect($routing->operations()->count())->toBe(2);
    expect($routing->is_default)->toBeTrue();

    $times = $routing->estimatedTimes();
    expect($times['setup'])->toBe(15.0);
    expect($times['run'])->toBe(10.0);
});

test('only finished and semi finished products can have routings', function () {
    $raw = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'WOOD-RT',
        'name' => 'Wood',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Raw Material',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this, ['product_id' => $raw->id]))
        ->assertSessionHasErrors('product_id');
});

test('machine must belong to selected work center', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this, [
            'operations' => [
                [
                    'sequence' => 10,
                    'operation_id' => $this->cut->id,
                    'work_center_id' => $this->assemblyWc->id,
                    'machine_id' => $this->machine->id,
                    'setup_time_minutes' => 1,
                    'run_time_per_unit' => 1,
                ],
            ],
        ]))
        ->assertSessionHasErrors('operations.0.machine_id');
});

test('duplicate sequences are rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this, [
            'operations' => [
                [
                    'sequence' => 10,
                    'operation_id' => $this->cut->id,
                    'work_center_id' => $this->workCenter->id,
                    'run_time_per_unit' => 1,
                ],
                [
                    'sequence' => 10,
                    'operation_id' => $this->assemble->id,
                    'work_center_id' => $this->assemblyWc->id,
                    'run_time_per_unit' => 1,
                ],
            ],
        ]))
        ->assertSessionHasErrors('operations.1.sequence');
});

test('released routings cannot be edited', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this))
        ->assertRedirect();

    $routing = RoutingHeader::first();

    $this->actingAs($this->admin)
        ->post(route('routings.release', $routing))
        ->assertRedirect();

    expect($routing->fresh()->status)->toBe('Released');

    $this->actingAs($this->admin)
        ->put(route('routings.update', $routing), validRoutingPayload($this, ['version' => '1.1']))
        ->assertSessionHasErrors('status');
});

test('admin can copy a routing to the next draft version', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this, ['status' => 'Draft']))
        ->assertRedirect();

    $source = RoutingHeader::first();

    $this->actingAs($this->admin)
        ->post(route('routings.release', $source))
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('routings.copy', $source))
        ->assertRedirect();

    $copy = RoutingHeader::where('version', '1.1')->first();
    expect($copy)->not->toBeNull();
    expect($copy->status)->toBe('Draft');
    expect($copy->operations()->count())->toBe(2);
});

test('only one default routing per product in plant', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this, ['version' => '1.0']))
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this, [
            'version' => '2.0',
            'is_default' => true,
        ]))
        ->assertRedirect();

    expect(RoutingHeader::where('product_id', $this->chair->id)->where('is_default', true)->count())->toBe(1);
    expect(RoutingHeader::where('version', '2.0')->value('is_default'))->toBeTruthy();
});

test('run time must be greater than zero', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this, [
            'operations' => [
                [
                    'sequence' => 10,
                    'operation_id' => $this->cut->id,
                    'work_center_id' => $this->workCenter->id,
                    'run_time_per_unit' => 0,
                ],
            ],
        ]))
        ->assertSessionHasErrors('operations.0.run_time_per_unit');
});

test('cannot release a routing with no operations', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this))
        ->assertRedirect();

    $routing = RoutingHeader::first();
    $routing->operations()->delete();

    $this->actingAs($this->admin)
        ->post(route('routings.release', $routing))
        ->assertSessionHasErrors('operations');

    expect($routing->fresh()->status)->toBe('Draft');
});

test('cannot release a routing with an inactive work center', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this))
        ->assertRedirect();

    $routing = RoutingHeader::first();
    $this->workCenter->update(['status' => 'Inactive']);

    $this->actingAs($this->admin)
        ->post(route('routings.release', $routing))
        ->assertSessionHasErrors('operations.0.work_center_id');

    expect($routing->fresh()->status)->toBe('Draft');
});

test('cannot release a routing with a non-assignable machine', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this))
        ->assertRedirect();

    $routing = RoutingHeader::first();
    $this->machine->update(['status' => 'Retired']);

    $this->actingAs($this->admin)
        ->post(route('routings.release', $routing))
        ->assertSessionHasErrors('operations.0.machine_id');

    expect($routing->fresh()->status)->toBe('Draft');
});

test('cannot release a routing with an inactive operation master', function () {
    $this->actingAs($this->admin)
        ->post(route('routings.store'), validRoutingPayload($this))
        ->assertRedirect();

    $routing = RoutingHeader::first();
    $this->cut->update(['status' => 'Inactive']);

    $this->actingAs($this->admin)
        ->post(route('routings.release', $routing))
        ->assertSessionHasErrors('operations.0.operation_id');

    expect($routing->fresh()->status)->toBe('Draft');
});

test('view-only users cannot create routings', function () {
    $role = Role::create([
        'name' => 'Routing Viewer',
        'slug' => 'routing-viewer-'.uniqid(),
        'description' => 'View only',
    ]);
    $role->permissions()->sync([
        Permission::where('slug', 'routing.view')->value('id'),
        Permission::where('slug', 'admin-dashboard')->value('id'),
    ]);

    $viewer = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $viewer->roles()->attach($role);

    $this->actingAs($viewer)->get(route('routings.index'))->assertOk();
    $this->actingAs($viewer)->post(route('routings.store'), validRoutingPayload($this))->assertForbidden();
});
