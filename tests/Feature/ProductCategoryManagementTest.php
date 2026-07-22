<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Category Test Org']);
    $this->otherOrg = Organization::firstOrCreate(['name' => 'Category Other Org']);

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

function createProductCategory(User $user, array $overrides = []): ProductCategory
{
    return ProductCategory::create(array_merge([
        'organization_id' => $user->organization_id,
        'code' => 'RAW',
        'name' => 'Raw Materials',
        'status' => 'Active',
        'sort_order' => 0,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

test('admin can view product categories for their organization only', function () {
    createProductCategory($this->admin);
    createProductCategory($this->otherAdmin, [
        'code' => 'RAW',
        'name' => 'Other Org Raw',
    ]);

    $this->actingAs($this->admin)
        ->get(route('product-categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('product-categories/index')
            ->has('categories.data', 1)
            ->where('categories.data.0.code', 'RAW')
            ->has('parentOptions')
            ->has('statuses')
        );
});

test('admin can create a root category', function () {
    $this->actingAs($this->admin)
        ->post(route('product-categories.store'), [
            'code' => 'fg',
            'name' => 'Finished Goods',
            'description' => 'Sellable products',
            'sort_order' => 10,
            'status' => 'Active',
        ])
        ->assertRedirect();

    $category = ProductCategory::where('code', 'FG')->first();
    expect($category)->not->toBeNull();
    expect($category->organization_id)->toBe($this->org->id);
    expect($category->parent_id)->toBeNull();
    expect($category->canBeAssignedToProducts())->toBeTrue();
});

test('admin can create a child category', function () {
    $parent = createProductCategory($this->admin, ['code' => 'RAW', 'name' => 'Raw Materials']);

    $this->actingAs($this->admin)
        ->post(route('product-categories.store'), [
            'parent_id' => $parent->id,
            'code' => 'STEEL',
            'name' => 'Steel',
            'status' => 'Active',
            'sort_order' => 1,
        ])
        ->assertRedirect();

    $child = ProductCategory::where('code', 'STEEL')->first();
    expect($child)->not->toBeNull();
    expect($child->parent_id)->toBe($parent->id);
});

test('admin can update a category parent', function () {
    $root = createProductCategory($this->admin, ['code' => 'FG', 'name' => 'Finished Goods']);
    $child = createProductCategory($this->admin, [
        'code' => 'ELEC',
        'name' => 'Electronics',
        'parent_id' => $root->id,
    ]);
    $newParent = createProductCategory($this->admin, ['code' => 'MECH', 'name' => 'Mechanical']);

    $this->actingAs($this->admin)
        ->put(route('product-categories.update', $child), [
            'parent_id' => $newParent->id,
            'code' => 'ELEC',
            'name' => 'Electronics',
            'status' => 'Active',
            'sort_order' => 0,
        ])
        ->assertRedirect();

    expect($child->fresh()->parent_id)->toBe($newParent->id);
});

test('category cannot be its own parent', function () {
    $category = createProductCategory($this->admin);

    $this->actingAs($this->admin)
        ->put(route('product-categories.update', $category), [
            'parent_id' => $category->id,
            'code' => 'RAW',
            'name' => 'Raw Materials',
            'status' => 'Active',
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('category cannot create a circular reference', function () {
    $root = createProductCategory($this->admin, ['code' => 'RAW', 'name' => 'Raw Materials']);
    $child = createProductCategory($this->admin, [
        'code' => 'STEEL',
        'name' => 'Steel',
        'parent_id' => $root->id,
    ]);
    $grandChild = createProductCategory($this->admin, [
        'code' => 'BAR',
        'name' => 'Steel Bar',
        'parent_id' => $child->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('product-categories.update', $root), [
            'parent_id' => $grandChild->id,
            'code' => 'RAW',
            'name' => 'Raw Materials',
            'status' => 'Active',
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('category code must be unique within the organization', function () {
    createProductCategory($this->admin, ['code' => 'RAW']);

    $this->actingAs($this->admin)
        ->post(route('product-categories.store'), [
            'code' => 'RAW',
            'name' => 'Duplicate',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->otherAdmin)
        ->post(route('product-categories.store'), [
            'code' => 'RAW',
            'name' => 'Raw Materials',
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect(ProductCategory::where('code', 'RAW')->count())->toBe(2);
});

test('inactive categories cannot be assigned to products', function () {
    $category = createProductCategory($this->admin, ['status' => 'Inactive']);

    expect($category->canBeAssignedToProducts())->toBeFalse();
});

test('admin can archive a category without products', function () {
    $category = createProductCategory($this->admin);

    $this->actingAs($this->admin)
        ->delete(route('product-categories.destroy', $category))
        ->assertRedirect();

    expect(ProductCategory::find($category->id))->toBeNull();
    expect(ProductCategory::withTrashed()->find($category->id))->not->toBeNull();
});

test('categories can be filtered by search parent and status', function () {
    $root = createProductCategory($this->admin, [
        'code' => 'RAW',
        'name' => 'Raw Materials',
        'status' => 'Active',
    ]);
    createProductCategory($this->admin, [
        'code' => 'STEEL',
        'name' => 'Steel',
        'parent_id' => $root->id,
        'status' => 'Inactive',
        'description' => 'Metal stock',
    ]);

    $this->actingAs($this->admin)
        ->get(route('product-categories.index', ['search' => 'Metal']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('categories.data', 1)->where('categories.data.0.code', 'STEEL'));

    $this->actingAs($this->admin)
        ->get(route('product-categories.index', ['parent_id' => $root->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('categories.data', 1)->where('categories.data.0.code', 'STEEL'));

    $this->actingAs($this->admin)
        ->get(route('product-categories.index', ['parent_id' => 'root']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('categories.data', 1)->where('categories.data.0.code', 'RAW'));

    $this->actingAs($this->admin)
        ->get(route('product-categories.index', ['status' => 'Inactive']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('categories.data', 1)->where('categories.data.0.code', 'STEEL'));
});

test('view-only users cannot mutate product categories', function () {
    $permission = Permission::where('slug', 'product-category.view')->firstOrFail();
    $role = Role::create([
        'name' => 'Category Viewer',
        'slug' => 'category-viewer-'.uniqid(),
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

    $category = createProductCategory($this->admin);

    $this->actingAs($viewer)->get(route('product-categories.index'))->assertOk();
    $this->actingAs($viewer)->post(route('product-categories.store'), [
        'code' => 'X',
        'name' => 'X',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->put(route('product-categories.update', $category), [
        'code' => 'RAW',
        'name' => 'Updated',
        'status' => 'Active',
    ])->assertForbidden();
    $this->actingAs($viewer)->delete(route('product-categories.destroy', $category))->assertForbidden();
});
