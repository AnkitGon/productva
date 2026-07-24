<?php

use App\Models\Operation;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Operation Test Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'OP1',
        'slug' => 'operation-test-plant',
    ], [
        'name' => 'Operation Test Plant',
        'is_default' => true,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);
});

test('admin can manage operations master', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('operations/index'));

    $this->actingAs($this->admin)
        ->post(route('operations.store'), [
            'code' => 'CUT',
            'name' => 'Cutting',
            'type' => 'Manufacturing',
            'status' => 'Active',
        ])
        ->assertRedirect();

    $operation = Operation::where('code', 'CUT')->first();
    expect($operation)->not->toBeNull();
    expect($operation->type)->toBe('Manufacturing');

    $this->actingAs($this->admin)
        ->put(route('operations.update', $operation), [
            'code' => 'CUT',
            'name' => 'Cut Wood',
            'type' => 'Manufacturing',
            'status' => 'Active',
        ])
        ->assertRedirect();

    expect($operation->fresh()->name)->toBe('Cut Wood');
});

test('operation codes are unique per organization', function () {
    $this->actingAs($this->admin)
        ->post(route('operations.store'), [
            'code' => 'WELD',
            'name' => 'Welding',
            'type' => 'Manufacturing',
            'status' => 'Active',
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('operations.store'), [
            'code' => 'WELD',
            'name' => 'Welding 2',
            'type' => 'Manufacturing',
            'status' => 'Active',
        ])
        ->assertSessionHasErrors('code');
});

test('view-only users cannot create operations', function () {
    $role = Role::create([
        'name' => 'Ops Viewer',
        'slug' => 'ops-viewer-'.uniqid(),
        'description' => 'View only',
    ]);
    $role->permissions()->sync([
        Permission::where('slug', 'operations.view')->value('id'),
        Permission::where('slug', 'admin-dashboard')->value('id'),
    ]);

    $viewer = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $viewer->roles()->attach($role);

    $this->actingAs($viewer)->get(route('operations.index'))->assertOk();
    $this->actingAs($viewer)->post(route('operations.store'), [
        'code' => 'PACK',
        'name' => 'Packaging',
        'type' => 'Packaging',
        'status' => 'Active',
    ])->assertForbidden();
});
