<?php

use App\Models\InventoryTransaction;
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
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Inventory Tx Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'ITX1',
        'slug' => 'inventory-tx-plant',
    ], [
        'name' => 'Inventory Tx Plant',
        'is_default' => true,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    WarehouseType::ensureDefaultsFor($this->org->id);
    $rawType = WarehouseType::where('organization_id', $this->org->id)->where('code', 'RAW')->firstOrFail();

    $this->warehouse = Warehouse::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_type_id' => $rawType->id,
        'code' => 'TX-WH',
        'name' => 'Tx Warehouse',
        'allow_negative_stock' => false,
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->location = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Bin',
        'code' => 'T-01',
        'name' => 'Bin T-01',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $category = ProductCategory::create([
        'organization_id' => $this->org->id,
        'code' => 'TXC',
        'name' => 'Tx Category',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $uom = UnitOfMeasure::create([
        'organization_id' => $this->org->id,
        'code' => 'EA',
        'name' => 'Each',
        'symbol' => 'ea',
        'type' => 'Count',
        'decimal_places' => 0,
        'status' => 'Active',
    ]);

    $this->product = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'NUT-M8',
        'name' => 'Nut M8',
        'category_id' => $category->id,
        'uom_id' => $uom->id,
        'type' => 'Raw Material',
        'status' => 'Active',
        'track_inventory' => true,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);
});

test('admin can view inventory transactions ledger', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 12,
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->get(route('inventory-transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory-transactions/index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.transaction_no', 'ADJ-000001')
            ->where('transactions.data.0.transaction_type', 'Adjustment')
        );
});

test('inventory transactions can be filtered by product', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 5,
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->get(route('inventory-transactions.index', ['product_id' => $this->product->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transactions.data', 1));

    $this->actingAs($this->admin)
        ->get(route('inventory-transactions.index', ['product_id' => 999999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transactions.data', 0));
});

test('inventory transactions search matches transaction numbers', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 3,
        ])
        ->assertRedirect();

    expect(InventoryTransaction::first()->transaction_no)->toBe('ADJ-000001');

    $this->actingAs($this->admin)
        ->get(route('inventory-transactions.index', ['search' => 'ADJ-000001']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transactions.data', 1));
});
