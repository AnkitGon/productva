<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'UOM Test Org']);
    $this->otherOrg = Organization::firstOrCreate(['name' => 'UOM Other Org']);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->otherAdmin = User::factory()->create([
        'organization_id' => $this->otherOrg->id,
    ]);
    $this->otherAdmin->roles()->sync([$adminRole->id]);
});

function createUnitOfMeasure(User $user, array $overrides = []): UnitOfMeasure
{
    return UnitOfMeasure::create(array_merge([
        'organization_id' => $user->organization_id,
        'code' => 'PCS',
        'name' => 'Pieces',
        'symbol' => 'pcs',
        'type' => 'Count',
        'decimal_places' => 0,
        'status' => 'Active',
    ], $overrides));
}

test('admin can view units of measure for their organization only', function () {
    createUnitOfMeasure($this->admin);
    createUnitOfMeasure($this->otherAdmin, [
        'code' => 'PCS',
        'name' => 'Other Org Pieces',
    ]);

    $this->actingAs($this->admin)
        ->get(route('units-of-measure.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('units-of-measure/index')
            ->has('unitsOfMeasure.data', 1)
            ->where('unitsOfMeasure.data.0.code', 'PCS')
            ->has('types')
            ->has('statuses')
        );
});

test('admin can create a unit of measure for the organization', function () {
    $this->actingAs($this->admin)
        ->post(route('units-of-measure.store'), [
            'code' => 'kg',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'Weight',
            'decimal_places' => 3,
            'status' => 'Active',
            'description' => 'Metric weight',
        ])
        ->assertRedirect();

    $unit = UnitOfMeasure::where('code', 'KG')->first();
    expect($unit)->not->toBeNull();
    expect($unit->organization_id)->toBe($this->org->id);
    expect($unit->type)->toBe('Weight');
    expect($unit->decimal_places)->toBe(3);
    expect($unit->canBeAssignedToProducts())->toBeTrue();
});

test('admin can update a unit of measure', function () {
    $unit = createUnitOfMeasure($this->admin);

    $this->actingAs($this->admin)
        ->put(route('units-of-measure.update', $unit), [
            'code' => 'PCS',
            'name' => 'Piece',
            'symbol' => 'pc',
            'type' => 'Count',
            'decimal_places' => 0,
            'status' => 'Inactive',
            'description' => 'Updated',
        ])
        ->assertRedirect();

    $unit->refresh();
    expect($unit->name)->toBe('Piece');
    expect($unit->symbol)->toBe('pc');
    expect($unit->status)->toBe('Inactive');
    expect($unit->canBeAssignedToProducts())->toBeFalse();
});

test('admin can archive an unused unit of measure', function () {
    $unit = createUnitOfMeasure($this->admin);

    $this->actingAs($this->admin)
        ->delete(route('units-of-measure.destroy', $unit))
        ->assertRedirect();

    expect(UnitOfMeasure::find($unit->id))->toBeNull();
    expect(UnitOfMeasure::withTrashed()->find($unit->id))->not->toBeNull();
});

test('unit of measure code must be unique within the organization', function () {
    createUnitOfMeasure($this->admin, ['code' => 'PCS']);

    $this->actingAs($this->admin)
        ->post(route('units-of-measure.store'), [
            'code' => 'PCS',
            'name' => 'Duplicate',
            'symbol' => 'pcs',
            'type' => 'Count',
            'decimal_places' => 0,
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->otherAdmin)
        ->post(route('units-of-measure.store'), [
            'code' => 'PCS',
            'name' => 'Pieces',
            'symbol' => 'pcs',
            'type' => 'Count',
            'decimal_places' => 0,
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(UnitOfMeasure::where('code', 'PCS')->count())->toBe(2);
});

test('unit of measure name must be unique within the organization', function () {
    createUnitOfMeasure($this->admin, [
        'code' => 'PCS',
        'name' => 'Pieces',
    ]);

    $this->actingAs($this->admin)
        ->post(route('units-of-measure.store'), [
            'code' => 'EA',
            'name' => 'Pieces',
            'symbol' => 'ea',
            'type' => 'Count',
            'decimal_places' => 0,
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->otherAdmin)
        ->post(route('units-of-measure.store'), [
            'code' => 'PCS',
            'name' => 'Pieces',
            'symbol' => 'pcs',
            'type' => 'Count',
            'decimal_places' => 0,
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(UnitOfMeasure::where('name', 'Pieces')->count())->toBe(2);
});

test('unit of measure name can be reused after soft delete', function () {
    $unit = createUnitOfMeasure($this->admin, [
        'code' => 'PCS',
        'name' => 'Pieces',
    ]);
    $unit->delete();

    $this->actingAs($this->admin)
        ->post(route('units-of-measure.store'), [
            'code' => 'PCS',
            'name' => 'Pieces',
            'symbol' => 'pcs',
            'type' => 'Count',
            'decimal_places' => 0,
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(UnitOfMeasure::where('name', 'Pieces')->count())->toBe(1);
});

test('units of measure index includes product counts', function () {
    $unit = createUnitOfMeasure($this->admin, [
        'code' => 'PCS',
        'name' => 'Pieces',
    ]);

    $category = ProductCategory::create([
        'organization_id' => $this->org->id,
        'code' => 'FG',
        'name' => 'Finished Goods',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    Product::create([
        'organization_id' => $this->org->id,
        'sku' => 'WID-1',
        'name' => 'Widget',
        'category_id' => $category->id,
        'uom_id' => $unit->id,
        'type' => 'Finished Good',
        'status' => 'Active',
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('units-of-measure.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('units-of-measure/index')
            ->where('unitsOfMeasure.data.0.products_count', 1)
        );
});

test('decimal places must be between 0 and 6', function () {
    $this->actingAs($this->admin)
        ->post(route('units-of-measure.store'), [
            'code' => 'BAD',
            'name' => 'Bad',
            'symbol' => 'bad',
            'type' => 'Count',
            'decimal_places' => 7,
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('decimal_places');
});

test('units can be filtered by search type and status', function () {
    createUnitOfMeasure($this->admin, [
        'code' => 'PCS',
        'name' => 'Pieces',
        'symbol' => 'pcs',
        'type' => 'Count',
        'status' => 'Active',
    ]);
    createUnitOfMeasure($this->admin, [
        'code' => 'KG',
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'type' => 'Weight',
        'status' => 'Inactive',
        'description' => 'Metric mass',
    ]);

    $this->actingAs($this->admin)
        ->get(route('units-of-measure.index', ['search' => 'Metric']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('unitsOfMeasure.data', 1)->where('unitsOfMeasure.data.0.code', 'KG'));

    $this->actingAs($this->admin)
        ->get(route('units-of-measure.index', ['type' => 'Weight']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('unitsOfMeasure.data', 1)->where('unitsOfMeasure.data.0.code', 'KG'));

    $this->actingAs($this->admin)
        ->get(route('units-of-measure.index', ['status' => 'Active']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('unitsOfMeasure.data', 1)->where('unitsOfMeasure.data.0.code', 'PCS'));
});

test('cannot mutate another organizations unit of measure', function () {
    $otherUnit = createUnitOfMeasure($this->otherAdmin, ['code' => 'LTR', 'name' => 'Liter', 'symbol' => 'L', 'type' => 'Volume']);

    $this->actingAs($this->admin)
        ->put(route('units-of-measure.update', $otherUnit), [
            'code' => 'LTR',
            'name' => 'Hacked',
            'symbol' => 'L',
            'type' => 'Volume',
            'decimal_places' => 2,
            'status' => 'Active',
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('units-of-measure.destroy', $otherUnit))
        ->assertForbidden();
});

test('view-only users cannot mutate units of measure', function () {
    $permission = Permission::where('slug', 'uom.view')->firstOrFail();
    $role = Role::create([
        'name' => 'UOM Viewer',
        'slug' => 'uom-viewer-'.uniqid(),
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

    $unit = createUnitOfMeasure($this->admin);

    $this->actingAs($viewer)->get(route('units-of-measure.index'))->assertOk();
    $this->actingAs($viewer)->post(route('units-of-measure.store'), [
        'code' => 'X',
        'name' => 'X',
        'symbol' => 'x',
        'type' => 'Count',
        'decimal_places' => 0,
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->put(route('units-of-measure.update', $unit), [
        'code' => 'PCS',
        'name' => 'Updated',
        'symbol' => 'pcs',
        'type' => 'Count',
        'decimal_places' => 0,
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->delete(route('units-of-measure.destroy', $unit))->assertForbidden();
});
