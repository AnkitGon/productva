<?php

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Organization;
use App\Models\Permission;
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

    $this->org = Organization::firstOrCreate(['name' => 'Inventory Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'INV1',
        'slug' => 'inventory-test-plant',
    ], [
        'name' => 'Inventory Test Plant',
        'is_default' => true,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    WarehouseType::ensureDefaultsFor($this->org->id);
    $this->rawType = WarehouseType::where('organization_id', $this->org->id)->where('code', 'RAW')->firstOrFail();

    $this->warehouse = Warehouse::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_type_id' => $this->rawType->id,
        'code' => 'RM-WH',
        'name' => 'Raw Warehouse',
        'allow_negative_stock' => false,
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->location = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Bin',
        'code' => 'A-01',
        'name' => 'Bin A-01',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->locationB = WarehouseLocation::create([
        'organization_id' => $this->org->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'Bin',
        'code' => 'B-01',
        'name' => 'Bin B-01',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->category = ProductCategory::create([
        'organization_id' => $this->org->id,
        'code' => 'RAW',
        'name' => 'Raw Materials',
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

    $this->product = Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'BOLT-M10',
        'name' => 'Bolt M10',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Raw Material',
        'status' => 'Active',
        'track_inventory' => true,
        'reorder_level' => 100,
        'purchase_price' => 2.5,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);
});

test('admin can view inventory index with dashboard cards', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->has('dashboard')
            ->has('inventories')
            ->has('warehouses')
        );
});

test('stock adjustment sets absolute quantity and generates ADJ transaction number', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 500,
            'notes' => 'Initial receipt',
            'reference_number' => 'COUNT-001',
        ])
        ->assertRedirect();

    $inventory = Inventory::where('product_id', $this->product->id)->first();
    expect($inventory)->not->toBeNull();
    expect((float) $inventory->quantity_on_hand)->toBe(500.0);
    expect((float) $inventory->quantity_reserved)->toBe(0.0);
    expect($inventory->quantity_available)->toBe('500.0000');
    expect($inventory->stock_status)->toBe('In Stock');

    expect(InventoryTransaction::where('inventory_id', $inventory->id)->count())->toBe(1);
    $tx = InventoryTransaction::first();
    expect($tx->transaction_type)->toBe('Adjustment');
    expect($tx->transaction_no)->toBe('ADJ-000001');
    expect((float) $tx->quantity)->toBe(500.0);
    expect((float) $tx->quantity_after)->toBe(500.0);
    expect($tx->reference_number)->toBe('COUNT-001');
});

test('available quantity is on hand minus reserved', function () {
    $inventory = Inventory::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_id' => $this->warehouse->id,
        'warehouse_location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity_on_hand' => 500,
        'quantity_reserved' => 80,
    ]);

    expect($inventory->quantity_available)->toBe('420.0000');
});

test('stock adjustment can decrease quantity via new quantity', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 100,
        ])
        ->assertRedirect();

    $this->warehouse->update(['allow_negative_stock' => true]);

    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 60,
        ])
        ->assertRedirect();

    $inventory = Inventory::where('product_id', $this->product->id)->first();
    expect((float) $inventory->quantity_on_hand)->toBe(60.0);
    expect(InventoryTransaction::where('inventory_id', $inventory->id)->count())->toBe(2);

    $second = InventoryTransaction::orderByDesc('id')->first();
    expect((float) $second->quantity)->toBe(-40.0);
    expect((float) $second->quantity_after)->toBe(60.0);
    expect($second->transaction_no)->toBe('ADJ-000002');
});

test('negative stock is blocked when warehouse does not allow it', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => -10,
        ])
        ->assertSessionHasErrors('new_quantity');
});

test('lot number is required when product has lot tracking', function () {
    $this->product->update(['lot_tracking' => true]);

    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 10,
        ])
        ->assertSessionHasErrors('lot_number');

    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 10,
            'lot_number' => 'LOT-100',
        ])
        ->assertRedirect();

    expect(Inventory::where('lot_number', 'LOT-100')->exists())->toBeTrue();
});

test('inventory history redirects to filtered transactions ledger', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 25,
        ])
        ->assertRedirect();

    $inventory = Inventory::first();

    $this->actingAs($this->admin)
        ->get(route('inventory.history', $inventory))
        ->assertRedirect(route('inventory-transactions.index', [
            'inventory_id' => $inventory->id,
            'product_id' => $inventory->product_id,
            'warehouse_id' => $inventory->warehouse_id,
            'warehouse_location_id' => $inventory->warehouse_location_id,
        ]));
});

test('inventory can be filtered by stock status', function () {
    Inventory::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_id' => $this->warehouse->id,
        'warehouse_location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
    ]);

    $this->actingAs($this->admin)
        ->get(route('inventory.index', ['stock_status' => 'Out Of Stock']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('inventories.data', 1));
});

test('inventory export returns csv', function () {
    Inventory::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_id' => $this->warehouse->id,
        'warehouse_location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity_on_hand' => 12,
        'quantity_reserved' => 2,
    ]);

    $this->actingAs($this->admin)
        ->get(route('inventory.export'))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('view-only users cannot adjust inventory', function () {
    $permission = Permission::where('slug', 'inventory.view')->firstOrFail();
    $role = Role::create([
        'name' => 'Inventory Viewer',
        'slug' => 'inventory-viewer-'.uniqid(),
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

    $this->actingAs($viewer)->get(route('inventory.index'))->assertOk();
    $this->actingAs($viewer)->post(route('inventory.adjust'), [
        'warehouse_id' => $this->warehouse->id,
        'warehouse_location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'new_quantity' => 5,
    ])->assertForbidden();
    $this->actingAs($viewer)->get(route('inventory.export'))->assertForbidden();
});

test('balance endpoint returns current on hand quantity', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 20,
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->getJson(route('inventory.balance', [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
        ]))
        ->assertOk()
        ->assertJson([
            'quantity_on_hand' => 20,
        ]);
});

test('transfer creates paired TRF transactions and updates both balances', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.adjust'), [
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'new_quantity' => 50,
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('inventory.transfer'), [
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouse->id,
            'from_location_id' => $this->location->id,
            'to_warehouse_id' => $this->warehouse->id,
            'to_location_id' => $this->locationB->id,
            'quantity' => 15,
        ])
        ->assertRedirect();

    $from = Inventory::where('warehouse_location_id', $this->location->id)->first();
    $to = Inventory::where('warehouse_location_id', $this->locationB->id)->first();

    expect((float) $from->quantity_on_hand)->toBe(35.0);
    expect((float) $to->quantity_on_hand)->toBe(15.0);

    $transferTx = InventoryTransaction::where('transaction_no', 'TRF-000001')->get();
    expect($transferTx)->toHaveCount(2);
    expect($transferTx->pluck('transaction_type')->sort()->values()->all())->toBe([
        'Transfer In',
        'Transfer Out',
    ]);
});

test('stock status uses minimum stock with strict less-than', function () {
    $this->product->update([
        'minimum_stock' => 10,
        'reorder_level' => null,
    ]);

    $exact = Inventory::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_id' => $this->warehouse->id,
        'warehouse_location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity_on_hand' => 10,
        'quantity_reserved' => 0,
    ]);
    $exact->load('product');
    expect($exact->stock_status)->toBe('In Stock');

    $low = Inventory::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_id' => $this->warehouse->id,
        'warehouse_location_id' => $this->locationB->id,
        'product_id' => $this->product->id,
        'quantity_on_hand' => 9,
        'quantity_reserved' => 0,
    ]);
    $low->load('product');
    expect($low->stock_status)->toBe('Low Stock');
});
