<?php

use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseType;
use App\Services\InventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Location Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'PL1',
        'slug' => 'location-test-plant',
    ], [
        'name' => 'Location Test Plant',
        'is_default' => true,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    WarehouseType::ensureDefaultsFor($this->org->id);
    $type = WarehouseType::where('organization_id', $this->org->id)->firstOrFail();

    $this->warehouse = Warehouse::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_type_id' => $type->id,
        'code' => 'WH-MAIN',
        'name' => 'Main Warehouse',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);
});

test('can list warehouse locations', function () {
    WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Zone',
        'code' => 'ZONE-A',
        'name' => 'Zone A',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('warehouse-locations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('warehouse-locations/index')
            ->has('locations.data', 1)
            ->where('locations.data.0.code', 'ZONE-A')
        );
});

test('can create nested warehouse locations', function () {
    $zone = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Zone',
        'code' => 'ZONE-A',
        'name' => 'Zone A',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('warehouse-locations.store'), [
            'warehouse_id' => $this->warehouse->id,
            'parent_id' => $zone->id,
            'type' => 'Aisle',
            'code' => 'AISLE-01',
            'name' => 'Aisle 1',
            'status' => 'Active',
        ])
        ->assertRedirect();

    $aisle = WarehouseLocation::where('code', 'AISLE-01')->firstOrFail();
    expect($aisle->parent_id)->toBe($zone->id);
    expect($aisle->type)->toBe('Aisle');
    expect($aisle->full_path)->toBe('Zone A > Aisle 1');
});

test('validates unique location code per warehouse', function () {
    WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Zone',
        'code' => 'ZONE-A',
        'name' => 'Zone A',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('warehouse-locations.store'), [
            'warehouse_id' => $this->warehouse->id,
            'type' => 'Zone',
            'code' => 'zone-a', // uppercase check
            'name' => 'Duplicate Zone',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');
});

test('prevents setting location as its own parent', function () {
    $loc = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Zone',
        'code' => 'ZONE-A',
        'name' => 'Zone A',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('warehouse-locations.update', $loc), [
            'warehouse_id' => $this->warehouse->id,
            'parent_id' => $loc->id,
            'type' => 'Zone',
            'code' => 'ZONE-A',
            'name' => 'Zone A Updated',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('parent_id');
});

test('prevents deleting location with active child locations', function () {
    $parent = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Zone',
        'code' => 'ZONE-A',
        'name' => 'Zone A',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'parent_id' => $parent->id,
        'type' => 'Aisle',
        'code' => 'AISLE-01',
        'name' => 'Aisle 1',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('warehouse-locations.destroy', $parent))
        ->assertRedirect();

    expect(WarehouseLocation::find($parent->id))->not->toBeNull();
});

test('can delete a leaf warehouse location', function () {
    $loc = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Bin',
        'code' => 'BIN-99',
        'name' => 'Bin 99',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('warehouse-locations.destroy', $loc))
        ->assertRedirect();

    expect(WarehouseLocation::find($loc->id))->toBeNull();
    expect(WarehouseLocation::withTrashed()->find($loc->id))->not->toBeNull();
});

test('warehouse location business rules: BL-3, BL-6', function () {
    $loc = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Bin',
        'code' => 'BIN-10',
        'name' => 'Bin 10',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    // Create a product
    $category = ProductCategory::create([
        'organization_id' => $this->org->id,
        'code' => 'TEST-CAT-'.uniqid(),
        'name' => 'Test Category',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $uom = UnitOfMeasure::create([
        'organization_id' => $this->org->id,
        'code' => 'PCS-'.uniqid(),
        'name' => 'Pieces',
        'type' => 'Count',
        'symbol' => 'pcs',
        'decimal_places' => 0,
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $product = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'TEST-SKU-'.uniqid(),
        'name' => 'Test Product',
        'category_id' => $category->id,
        'uom_id' => $uom->id,
        'type' => 'Raw Material',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    // BL-3: Bin with stock cannot be deleted
    $inventory = Inventory::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_id' => $this->warehouse->id,
        'warehouse_location_id' => $loc->id,
        'product_id' => $product->id,
        'quantity_on_hand' => 5,
    ]);

    expect($loc->hasStock())->toBeTrue();

    // Destroy should be blocked and not deleted
    $this->actingAs($this->admin)
        ->delete(route('warehouse-locations.destroy', $loc))
        ->assertRedirect();

    expect(WarehouseLocation::find($loc->id))->not->toBeNull();

    // Empty stock
    $inventory->update(['quantity_on_hand' => 0]);
    expect($loc->hasStock())->toBeFalse();

    // Now it can be deleted
    $this->actingAs($this->admin)
        ->delete(route('warehouse-locations.destroy', $loc))
        ->assertRedirect();
    expect(WarehouseLocation::find($loc->id))->toBeNull();

    // BL-6: Location status affects transactions (cannot adjust or transfer on inactive location)
    $loc2 = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Bin',
        'code' => 'BIN-11',
        'name' => 'Bin 11',
        'status' => 'Inactive',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $service = app(InventoryService::class);

    expect(fn () => $service->adjust($this->admin, [
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'warehouse_location_id' => $loc2->id,
        'new_quantity' => 20,
    ]))->toThrow(ValidationException::class);
});
