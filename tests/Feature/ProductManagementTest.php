<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Product Test Org']);
    $this->otherOrg = Organization::firstOrCreate(['name' => 'Product Other Org']);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->otherAdmin = User::factory()->create([
        'organization_id' => $this->otherOrg->id,
    ]);
    $this->otherAdmin->roles()->sync([$adminRole->id]);

    $this->category = ProductCategory::create([
        'organization_id' => $this->org->id,
        'code' => 'FG',
        'name' => 'Finished Goods',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->inactiveCategory = ProductCategory::create([
        'organization_id' => $this->org->id,
        'code' => 'OLD',
        'name' => 'Inactive Category',
        'status' => 'Inactive',
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

    $this->inactiveUom = UnitOfMeasure::create([
        'organization_id' => $this->org->id,
        'code' => 'OLD',
        'name' => 'Old UOM',
        'symbol' => 'old',
        'type' => 'Count',
        'decimal_places' => 0,
        'status' => 'Inactive',
    ]);

    $this->otherCategory = ProductCategory::create([
        'organization_id' => $this->otherOrg->id,
        'code' => 'FG',
        'name' => 'Other FG',
        'status' => 'Active',
        'created_by' => $this->otherAdmin->id,
        'updated_by' => $this->otherAdmin->id,
    ]);

    $this->otherUom = UnitOfMeasure::create([
        'organization_id' => $this->otherOrg->id,
        'code' => 'PCS',
        'name' => 'Pieces',
        'symbol' => 'pcs',
        'type' => 'Count',
        'decimal_places' => 0,
        'status' => 'Active',
    ]);
});

function createProduct(User $user, ProductCategory $category, UnitOfMeasure $uom, array $overrides = []): Product
{
    return Product::create(array_merge([
        'organization_id' => $user->organization_id,
        'sku' => 'SKU-001',
        'name' => 'Widget',
        'category_id' => $category->id,
        'uom_id' => $uom->id,
        'type' => 'Finished Good',
        'status' => 'Active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

test('admin can view products for their organization only', function () {
    createProduct($this->admin, $this->category, $this->uom);
    createProduct($this->otherAdmin, $this->otherCategory, $this->otherUom, [
        'sku' => 'SKU-001',
        'name' => 'Other Widget',
    ]);

    $this->actingAs($this->admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/index')
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'SKU-001')
        );
});

test('admin can create products of each type', function () {
    foreach (Product::TYPES as $index => $type) {
        $this->actingAs($this->admin)
            ->post(route('products.store'), [
                'sku' => 'TYPE-'.$index,
                'name' => $type.' Item',
                'category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'type' => $type,
                'status' => 'Active',
                'track_inventory' => true,
            ])
            ->assertRedirect(route('products.index'));
    }

    expect(Product::where('organization_id', $this->org->id)->count())->toBe(count(Product::TYPES));
});

test('sku must be unique within the organization', function () {
    createProduct($this->admin, $this->category, $this->uom, ['sku' => 'SKU-001']);

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'SKU-001',
            'name' => 'Duplicate',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('sku');

    $this->actingAs($this->otherAdmin)
        ->post(route('products.store'), [
            'sku' => 'SKU-001',
            'name' => 'Other Org Product',
            'category_id' => $this->otherCategory->id,
            'uom_id' => $this->otherUom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
        ])
        ->assertRedirect(route('products.index'));
});

test('barcode must be unique within the organization when provided', function () {
    createProduct($this->admin, $this->category, $this->uom, [
        'sku' => 'SKU-001',
        'barcode' => 'BC-100',
    ]);

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'SKU-002',
            'barcode' => 'BC-100',
            'name' => 'Duplicate Barcode',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('barcode');
});

test('category and uom must be active for new products', function () {
    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'BAD-CAT',
            'name' => 'Bad Category',
            'category_id' => $this->inactiveCategory->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('category_id');

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'BAD-UOM',
            'name' => 'Bad UOM',
            'category_id' => $this->category->id,
            'uom_id' => $this->inactiveUom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('uom_id');
});

test('lot tracking and serial tracking cannot both be enabled', function () {
    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'TRACE-1',
            'name' => 'Trace Product',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'lot_tracking' => true,
            'serial_tracking' => true,
        ])
        ->assertSessionHasErrors('serial_tracking');
});

test('admin can update a product', function () {
    $product = createProduct($this->admin, $this->category, $this->uom);

    $this->actingAs($this->admin)
        ->put(route('products.update', $product), [
            'sku' => 'SKU-001',
            'name' => 'Updated Widget',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Semi Finished',
            'status' => 'Active',
            'track_inventory' => true,
            'lot_tracking' => true,
            'serial_tracking' => false,
        ])
        ->assertRedirect(route('products.index'));

    $product->refresh();
    expect($product->name)->toBe('Updated Widget');
    expect($product->type)->toBe('Semi Finished');
    expect($product->lot_tracking)->toBeTrue();
});

test('admin can archive a product without blocking dependencies', function () {
    $product = createProduct($this->admin, $this->category, $this->uom);

    $this->actingAs($this->admin)
        ->delete(route('products.destroy', $product))
        ->assertRedirect();

    expect(Product::find($product->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

test('products can be filtered and searched', function () {
    createProduct($this->admin, $this->category, $this->uom, [
        'sku' => 'FG-100',
        'name' => 'Finished Widget',
        'barcode' => 'BC-FG',
        'type' => 'Finished Good',
        'track_inventory' => true,
        'lot_tracking' => true,
    ]);
    createProduct($this->admin, $this->category, $this->uom, [
        'sku' => 'RM-100',
        'name' => 'Steel Rod',
        'supplier_sku' => 'SUP-STEEL',
        'type' => 'Raw Material',
        'track_inventory' => false,
        'serial_tracking' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'SUP-STEEL']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.sku', 'RM-100'));

    $this->actingAs($this->admin)
        ->get(route('products.index', ['type' => 'Finished Good']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.sku', 'FG-100'));

    $this->actingAs($this->admin)
        ->get(route('products.index', ['track_inventory' => '0']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.sku', 'RM-100'));
});

test('products can be filtered by warehouse manufacturer and brand', function () {
    $plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'PPF1',
        'slug' => 'product-filter-plant',
    ], [
        'name' => 'Product Filter Plant',
    ]);

    WarehouseType::ensureDefaultsFor($this->org->id);
    $warehouseType = WarehouseType::query()
        ->where('organization_id', $this->org->id)
        ->where('code', 'RAW')
        ->firstOrFail();

    $warehouse = Warehouse::create([
        'organization_id' => $this->org->id,
        'plant_id' => $plant->id,
        'warehouse_type_id' => $warehouseType->id,
        'code' => 'WH-F1',
        'name' => 'Filter Warehouse',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    createProduct($this->admin, $this->category, $this->uom, [
        'sku' => 'BRAND-1',
        'name' => 'Branded Item',
        'brand' => 'Acme',
        'manufacturer' => 'Acme Corp',
        'default_warehouse_id' => $warehouse->id,
    ]);
    createProduct($this->admin, $this->category, $this->uom, [
        'sku' => 'OTHER-1',
        'name' => 'Other Item',
        'brand' => 'Beta',
        'manufacturer' => 'Beta Inc',
    ]);

    $this->actingAs($this->admin)
        ->get(route('products.index', ['brand' => 'Acme']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.sku', 'BRAND-1'));

    $this->actingAs($this->admin)
        ->get(route('products.index', ['manufacturer' => 'Acme Corp']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.sku', 'BRAND-1'));

    $this->actingAs($this->admin)
        ->get(route('products.index', ['default_warehouse_id' => $warehouse->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.sku', 'BRAND-1'));
});

test('admin can store opening stock and opening cost', function () {
    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'OPEN-1',
            'name' => 'Opening Stock Item',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'track_inventory' => true,
            'opening_stock' => 25,
            'opening_cost' => 12.5,
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::query()->where('sku', 'OPEN-1')->first();

    expect($product)->not->toBeNull()
        ->and((float) $product->opening_stock)->toBe(25.0)
        ->and((float) $product->opening_cost)->toBe(12.5);
});

test('stock levels must follow maximum >= minimum >= safety <= reorder', function () {
    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'STOCK-BAD-1',
            'name' => 'Bad Max Min',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'track_inventory' => true,
            'maximum_stock' => 5,
            'minimum_stock' => 10,
        ])
        ->assertSessionHasErrors('maximum_stock');

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'STOCK-BAD-2',
            'name' => 'Bad Min Safety',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'track_inventory' => true,
            'minimum_stock' => 5,
            'safety_stock' => 10,
        ])
        ->assertSessionHasErrors('minimum_stock');

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'STOCK-BAD-3',
            'name' => 'Bad Safety Reorder',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'track_inventory' => true,
            'safety_stock' => 20,
            'reorder_level' => 10,
        ])
        ->assertSessionHasErrors('safety_stock');

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'STOCK-OK',
            'name' => 'Valid Stock Levels',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'track_inventory' => true,
            'maximum_stock' => 100,
            'minimum_stock' => 40,
            'safety_stock' => 20,
            'reorder_level' => 30,
        ])
        ->assertRedirect(route('products.index'));
});

test('turning off track inventory clears warehouse opening stock and negative stock', function () {
    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'NO-TRACK-1',
            'name' => 'Untracked Item',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Service',
            'status' => 'Active',
            'track_inventory' => false,
            'allow_negative_stock' => true,
            'opening_stock' => 50,
            'opening_cost' => 9.99,
            'default_warehouse_id' => null,
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::query()->where('sku', 'NO-TRACK-1')->first();

    expect($product)->not->toBeNull()
        ->and($product->track_inventory)->toBeFalse()
        ->and($product->allow_negative_stock)->toBeFalse()
        ->and($product->default_warehouse_id)->toBeNull()
        ->and((float) $product->opening_stock)->toBe(0.0)
        ->and((float) $product->opening_cost)->toBe(0.0);
});

test('admin can export and import products csv', function () {
    createProduct($this->admin, $this->category, $this->uom, [
        'sku' => 'EXP-1',
        'name' => 'Exportable',
        'barcode' => 'BC-EXP',
    ]);

    $this->actingAs($this->admin)
        ->get(route('products.export'))
        ->assertOk()
        ->assertHeader('content-disposition');

    $csv = "SKU,Name,Category,Type,UOM,Barcode,Status\nIMP-1,Imported Item,FG,Raw Material,PCS,BC-IMP,Active\n";
    $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

    $this->actingAs($this->admin)
        ->post(route('products.import'), ['file' => $file])
        ->assertRedirect();

    $imported = Product::where('sku', 'IMP-1')->first();
    expect($imported)->not->toBeNull();
    expect($imported->name)->toBe('Imported Item');
    expect($imported->type)->toBe('Raw Material');
    expect($imported->barcode)->toBe('BC-IMP');
});

test('product image can be uploaded', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'sku' => 'IMG-1',
            'name' => 'Imaged Product',
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'image' => UploadedFile::fake()->image('widget.jpg'),
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::where('sku', 'IMG-1')->first();
    expect($product->image_path)->not->toBeNull();
    expect($product->image_url)->toBe('/storage/'.$product->image_path);
    Storage::disk('public')->assertExists($product->image_path);
});

test('admin can view product details', function () {
    $product = createProduct($this->admin, $this->category, $this->uom, [
        'sku' => 'VIEW-1',
        'name' => 'Viewable Product',
        'description' => 'Full detail view',
        'lot_tracking' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/show')
            ->where('product.sku', 'VIEW-1')
            ->where('product.name', 'Viewable Product')
            ->where('product.lot_tracking', true)
            ->has('product.category')
            ->has('product.uom')
        );
});

test('category products count updates when products are created', function () {
    createProduct($this->admin, $this->category, $this->uom);

    expect($this->category->fresh()->hasProducts())->toBeTrue();
    expect($this->category->fresh()->products_count)->toBe(1);
    expect($this->uom->fresh()->isReferencedByProduct())->toBeTrue();
});

test('view-only users cannot mutate products', function () {
    $permission = Permission::where('slug', 'products.view')->firstOrFail();
    $role = Role::create([
        'name' => 'Product Viewer',
        'slug' => 'product-viewer-'.uniqid(),
        'description' => 'View only',
    ]);
    $role->permissions()->sync([
        $permission->id,
        Permission::where('slug', 'admin-dashboard')->value('id'),
    ]);

    $viewer = User::factory()->create([
        'organization_id' => $this->org->id,
    ]);
    $viewer->roles()->attach($role);

    $product = createProduct($this->admin, $this->category, $this->uom);

    $this->actingAs($viewer)->get(route('products.index'))->assertOk();
    $this->actingAs($viewer)->get(route('products.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('products.store'), [
        'sku' => 'X',
        'name' => 'X',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Finished Good',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->put(route('products.update', $product), [
        'sku' => 'SKU-001',
        'name' => 'Updated',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
        'type' => 'Finished Good',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->delete(route('products.destroy', $product))->assertForbidden();
    $this->actingAs($viewer)->get(route('products.export'))->assertForbidden();
    $this->actingAs($viewer)->post(route('products.import'), [
        'file' => UploadedFile::fake()->create('products.csv', 10, 'text/csv'),
    ])->assertForbidden();
});
