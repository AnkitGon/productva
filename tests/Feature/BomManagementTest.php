<?php

use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'BOM Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'BOM1',
        'slug' => 'bom-test-plant',
    ], [
        'name' => 'BOM Test Plant',
        'is_default' => true,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

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

    $this->kg = UnitOfMeasure::create([
        'organization_id' => $this->org->id,
        'code' => 'KG',
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'type' => 'Weight',
        'decimal_places' => 3,
        'status' => 'Active',
    ]);

    $this->chair = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'CHAIR-01',
        'name' => 'Wooden Chair',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Finished Good',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->wood = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'WOOD-01',
        'name' => 'Wood',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Raw Material',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->screw = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'SCR-01',
        'name' => 'Screw',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Raw Material',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->glue = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'GLUE-01',
        'name' => 'Glue',
        'category_id' => $this->category->id,
        'uom_id' => $this->kg->id,
        'type' => 'Raw Material',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);
});

function validBomPayload($test, array $overrides = []): array
{
    return array_merge([
        'product_id' => $test->chair->id,
        'version' => '1.0',
        'is_default' => true,
        'effective_from' => '2026-07-01',
        'effective_to' => null,
        'status' => 'Active',
        'notes' => 'Chair BOM',
        'items' => [
            [
                'component_product_id' => $test->wood->id,
                'quantity' => 5,
                'uom_id' => $test->uom->id,
                'scrap_percentage' => 0,
                'sequence' => 10,
            ],
            [
                'component_product_id' => $test->screw->id,
                'quantity' => 12,
                'uom_id' => $test->uom->id,
                'scrap_percentage' => 2,
                'sequence' => 20,
            ],
            [
                'component_product_id' => $test->glue->id,
                'quantity' => 0.25,
                'uom_id' => $test->kg->id,
                'scrap_percentage' => 0,
                'sequence' => 30,
            ],
        ],
    ], $overrides);
}

test('admin can view bom index', function () {
    $this->actingAs($this->admin)
        ->get(route('boms.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('boms/index')
            ->has('boms')
            ->has('statuses')
        );
});

test('admin can create a bom with components', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this))
        ->assertRedirect();

    $bom = BomHeader::where('product_id', $this->chair->id)->first();
    expect($bom)->not->toBeNull();
    expect($bom->version)->toBe('1.0');
    expect($bom->is_default)->toBeTrue();
    expect($bom->items()->count())->toBe(3);

    $woodLine = BomItem::where('bom_header_id', $bom->id)
        ->where('component_product_id', $this->wood->id)
        ->first();
    expect((float) $woodLine->quantity)->toBe(5.0);
});

test('only one default bom is allowed per product', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this, ['version' => '1.0']))
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this, [
            'version' => '2.0',
            'is_default' => true,
        ]))
        ->assertRedirect();

    expect(BomHeader::where('product_id', $this->chair->id)->where('is_default', true)->count())->toBe(1);
    expect(BomHeader::where('version', '2.0')->value('is_default'))->toBeTruthy();
    expect(BomHeader::where('version', '1.0')->value('is_default'))->toBeFalsy();
});

test('bom cannot contain the parent product as a component', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this, [
            'items' => [
                [
                    'component_product_id' => $this->chair->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'sequence' => 10,
                ],
            ],
        ]))
        ->assertSessionHasErrors();
});

test('duplicate components in the same bom are rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this, [
            'items' => [
                [
                    'component_product_id' => $this->wood->id,
                    'quantity' => 5,
                    'uom_id' => $this->uom->id,
                    'sequence' => 10,
                ],
                [
                    'component_product_id' => $this->wood->id,
                    'quantity' => 2,
                    'uom_id' => $this->uom->id,
                    'sequence' => 20,
                ],
            ],
        ]))
        ->assertSessionHasErrors('items.1.component_product_id');
});

test('circular boms are prevented', function () {
    $semi = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'SEAT-01',
        'name' => 'Chair Seat',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Semi Finished',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    // Seat uses Wood
    $this->actingAs($this->admin)
        ->post(route('boms.store'), [
            'product_id' => $semi->id,
            'version' => '1.0',
            'is_default' => true,
            'status' => 'Active',
            'items' => [
                [
                    'component_product_id' => $this->wood->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'sequence' => 10,
                ],
            ],
        ])
        ->assertRedirect();

    // Chair uses Seat
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this, [
            'items' => [
                [
                    'component_product_id' => $semi->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'sequence' => 10,
                ],
            ],
        ]))
        ->assertRedirect();

    // Wood cannot use Chair (A→B→C→A style cycle via wood → chair)
    $this->actingAs($this->admin)
        ->post(route('boms.store'), [
            'product_id' => $this->wood->id,
            'version' => '1.0',
            'is_default' => true,
            'status' => 'Active',
            'items' => [
                [
                    'component_product_id' => $this->chair->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'sequence' => 10,
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.component_product_id');
});

test('bom versions must be unique per product', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this))
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this))
        ->assertSessionHasErrors('version');
});

test('admin can update and view a bom', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this))
        ->assertRedirect();

    $bom = BomHeader::first();

    $this->actingAs($this->admin)
        ->get(route('boms.show', $bom))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('boms/show')
            ->has('bom.items', 3)
            ->has('summary')
            ->where('summary.components_count', 3)
            ->where('summary.estimated_material_cost', null)
        );

    $this->actingAs($this->admin)
        ->put(route('boms.update', $bom), validBomPayload($this, [
            'version' => '1.1',
            'status' => 'Active',
            'items' => [
                [
                    'component_product_id' => $this->wood->id,
                    'quantity' => 6,
                    'uom_id' => $this->uom->id,
                    'sequence' => 10,
                ],
            ],
        ]))
        ->assertRedirect(route('boms.show', $bom));

    expect($bom->fresh()->version)->toBe('1.1');
    expect($bom->items()->count())->toBe(1);
});

test('admin can delete a bom', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this))
        ->assertRedirect();

    $bom = BomHeader::first();

    $this->actingAs($this->admin)
        ->delete(route('boms.destroy', $bom))
        ->assertRedirect(route('boms.index'));

    expect(BomHeader::find($bom->id))->toBeNull();
    expect(BomHeader::withTrashed()->find($bom->id))->not->toBeNull();
});

test('view-only users cannot create boms', function () {
    $permission = Permission::where('slug', 'boms.view')->firstOrFail();
    $role = Role::create([
        'name' => 'BOM Viewer',
        'slug' => 'bom-viewer-'.uniqid(),
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

    $this->actingAs($viewer)->get(route('boms.index'))->assertOk();
    $this->actingAs($viewer)->post(route('boms.store'), validBomPayload($this))->assertForbidden();
});

test('effective_to must be on or after effective_from', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this, [
            'effective_from' => '2026-07-10',
            'effective_to' => '2026-07-01',
        ]))
        ->assertSessionHasErrors('effective_to');
});

test('admin can copy a bom into the next draft version', function () {
    $this->actingAs($this->admin)
        ->post(route('boms.store'), validBomPayload($this, [
            'version' => '1.0',
            'status' => 'Active',
        ]))
        ->assertRedirect();

    $source = BomHeader::first();

    $this->actingAs($this->admin)
        ->post(route('boms.copy', $source))
        ->assertRedirect();

    $copy = BomHeader::where('version', '1.1')->first();
    expect($copy)->not->toBeNull();
    expect($copy->status)->toBe('Draft');
    expect($copy->is_default)->toBeFalse();
    expect($copy->product_id)->toBe($source->product_id);
    expect($copy->items()->count())->toBe(3);
    expect($source->fresh()->version)->toBe('1.0');
});

test('direct circular bom bike wheel bike is prevented', function () {
    $bike = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'BIKE-01',
        'name' => 'Bike',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Finished Good',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $wheel = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'WHEEL-01',
        'name' => 'Wheel',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Semi Finished',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('boms.store'), [
            'product_id' => $bike->id,
            'version' => '1.0',
            'is_default' => true,
            'status' => 'Active',
            'items' => [
                [
                    'component_product_id' => $wheel->id,
                    'quantity' => 2,
                    'uom_id' => $this->uom->id,
                    'sequence' => 10,
                ],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('boms.store'), [
            'product_id' => $wheel->id,
            'version' => '1.0',
            'is_default' => true,
            'status' => 'Active',
            'items' => [
                [
                    'component_product_id' => $bike->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'sequence' => 10,
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.component_product_id');
});
