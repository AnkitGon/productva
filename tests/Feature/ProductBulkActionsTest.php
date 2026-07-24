<?php

use App\Models\Organization;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Product Bulk Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'PBP1',
        'slug' => 'product-bulk-plant',
    ], [
        'name' => 'Product Bulk Plant',
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

    $this->otherCategory = ProductCategory::create([
        'organization_id' => $this->org->id,
        'code' => 'RM',
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

    WarehouseType::ensureDefaultsFor($this->org->id);
    $warehouseType = WarehouseType::query()
        ->where('organization_id', $this->org->id)
        ->where('code', 'RAW')
        ->firstOrFail();

    $this->warehouse = Warehouse::create([
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'warehouse_type_id' => $warehouseType->id,
        'code' => 'WH-MAIN',
        'name' => 'Main Warehouse',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);
});

function makeBulkProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'organization_id' => test()->org->id,
        'sku' => 'BLK-'.uniqid(),
        'name' => 'Bulk Product',
        'category_id' => test()->category->id,
        'uom_id' => test()->uom->id,
        'type' => 'Finished Good',
        'status' => 'Inactive',
        'created_by' => test()->admin->id,
        'updated_by' => test()->admin->id,
    ], $overrides));
}

it('can activate selected products', function () {
    $one = makeBulkProduct(['status' => 'Inactive']);
    $two = makeBulkProduct(['status' => 'Inactive', 'sku' => 'BLK-2']);

    $this->actingAs($this->admin)->post(route('products.bulk'), [
        'action' => 'activate',
        'ids' => [$one->id, $two->id],
    ])->assertRedirect();

    expect($one->fresh()->status)->toBe('Active')
        ->and($two->fresh()->status)->toBe('Active');
});

it('can deactivate selected products', function () {
    $one = makeBulkProduct(['status' => 'Active']);

    $this->actingAs($this->admin)->post(route('products.bulk'), [
        'action' => 'deactivate',
        'ids' => [$one->id],
    ])->assertRedirect();

    expect($one->fresh()->status)->toBe('Inactive');
});

it('can assign a category to selected products', function () {
    $one = makeBulkProduct();
    $two = makeBulkProduct(['sku' => 'BLK-CAT-2']);

    $this->actingAs($this->admin)->post(route('products.bulk'), [
        'action' => 'assign_category',
        'ids' => [$one->id, $two->id],
        'category_id' => $this->otherCategory->id,
    ])->assertRedirect();

    expect($one->fresh()->category_id)->toBe($this->otherCategory->id)
        ->and($two->fresh()->category_id)->toBe($this->otherCategory->id);
});

it('can assign a warehouse to selected products', function () {
    $one = makeBulkProduct();

    $this->actingAs($this->admin)->post(route('products.bulk'), [
        'action' => 'assign_warehouse',
        'ids' => [$one->id],
        'default_warehouse_id' => $this->warehouse->id,
    ])->assertRedirect();

    expect($one->fresh()->default_warehouse_id)->toBe($this->warehouse->id);
});

it('can soft delete selected products', function () {
    $one = makeBulkProduct(['sku' => 'BLK-DEL-1']);

    $this->actingAs($this->admin)->post(route('products.bulk'), [
        'action' => 'delete',
        'ids' => [$one->id],
    ])->assertRedirect();

    $this->assertSoftDeleted('products', ['id' => $one->id]);
});

it('can export selected products as csv', function () {
    $one = makeBulkProduct(['sku' => 'BLK-EXP-1', 'name' => 'Export Me', 'status' => 'Active']);

    $response = $this->actingAs($this->admin)->post(route('products.bulk'), [
        'action' => 'export',
        'ids' => [$one->id],
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('BLK-EXP-1');
});
