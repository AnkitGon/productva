<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function plantManager(Organization $org, Plant $plant): User
{
    $user = User::create([
        'name' => 'Plant User',
        'email' => 'plant-user-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $org->id,
        'active_plant_id' => $plant->id,
    ]);

    $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

    return $user;
}

test('code and slug must be unique per organization', function () {
    $org1 = Organization::create(['name' => 'Org One']);
    $org2 = Organization::create(['name' => 'Org Two']);

    // Create a plant in Org 1
    Plant::create([
        'organization_id' => $org1->id,
        'name' => 'Main Plant',
        'code' => 'M01',
        'slug' => 'main-plant',
        'is_default' => true,
    ]);

    // Creating another plant in Org 1 with same code should fail unique constraint
    expect(fn () => Plant::create([
        'organization_id' => $org1->id,
        'name' => 'Other Plant',
        'code' => 'M01',
        'slug' => 'other-plant',
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Creating another plant in Org 1 with same slug should fail unique constraint
    expect(fn () => Plant::create([
        'organization_id' => $org1->id,
        'name' => 'Other Plant 2',
        'code' => 'M02',
        'slug' => 'main-plant',
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Different organization can use same code and slug
    $plantInOrg2 = Plant::create([
        'organization_id' => $org2->id,
        'name' => 'Main Plant',
        'code' => 'M01',
        'slug' => 'main-plant',
        'is_default' => true,
    ]);

    expect($plantInOrg2)->not->toBeNull();
});

test('only one default plant is allowed per organization', function () {
    $org = Organization::create(['name' => 'Test Org']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    expect($plant1->fresh()->is_default)->toBeTrue();

    // Create another default plant
    $plant2 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 2',
        'code' => 'P2',
        'slug' => 'plant-2',
        'is_default' => true,
    ]);

    expect($plant2->fresh()->is_default)->toBeTrue();
    expect($plant1->fresh()->is_default)->toBeFalse(); // Should have been unset automatically
});

test('authenticated user can switch active plant within their organization', function () {
    $org = Organization::create(['name' => 'Switch Org']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $plant2 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 2',
        'code' => 'P2',
        'slug' => 'plant-2',
        'is_default' => false,
    ]);

    $user = User::create([
        'name' => 'Plant User',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $org->id,
        'active_plant_id' => $plant1->id,
    ]);

    // Send request to switch to plant 2
    $response = $this->actingAs($user)->post(route('plants.activate', ['plant' => $plant2->id]));

    $response->assertRedirect();
    expect($user->fresh()->active_plant_id)->toBe($plant2->id);
});

test('user cannot switch to a plant of another organization', function () {
    $org1 = Organization::create(['name' => 'Org One']);
    $org2 = Organization::create(['name' => 'Org Two']);

    $plant1 = Plant::create([
        'organization_id' => $org1->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $plantOfOtherOrg = Plant::create([
        'organization_id' => $org2->id,
        'name' => 'Plant 2',
        'code' => 'P2',
        'slug' => 'plant-2',
        'is_default' => true,
    ]);

    $user = User::create([
        'name' => 'Plant User',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $org1->id,
        'active_plant_id' => $plant1->id,
    ]);

    // Try switching to other org's plant
    $response = $this->actingAs($user)->post(route('plants.activate', ['plant' => $plantOfOtherOrg->id]));

    // Should redirect back without updating active_plant_id
    $response->assertRedirect();
    expect($user->fresh()->active_plant_id)->toBe($plant1->id);
});

test('user can create a plant and it is automatically activated', function () {
    $org = Organization::create(['name' => 'Org One']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $user = plantManager($org, $plant1);

    $response = $this->actingAs($user)->post(route('plants.store'), [
        'name' => 'Plant 2',
        'code' => 'P2',
        'slug' => 'plant-2',
        'description' => 'A new plant',
        'status' => 'Active',
        'is_default' => false,
    ]);

    $response->assertRedirect();

    $newPlant = Plant::where('organization_id', $org->id)->where('code', 'P2')->first();
    expect($newPlant)->not->toBeNull();
    expect($user->fresh()->active_plant_id)->toBe($newPlant->id);
});

test('user can update a plant', function () {
    $org = Organization::create(['name' => 'Org One']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $user = plantManager($org, $plant1);

    $response = $this->actingAs($user)->put(route('plants.update', ['plant' => $plant1->id]), [
        'name' => 'Updated Plant 1',
        'code' => 'P1-NEW',
        'slug' => 'plant-1-updated',
        'description' => 'Updated description',
        'status' => 'Inactive',
        'is_default' => true,
    ]);

    $response->assertRedirect();

    $updated = $plant1->fresh();
    expect($updated->name)->toBe('Updated Plant 1');
    expect($updated->code)->toBe('P1-NEW');
    expect($updated->status)->toBe('Inactive');
});

test('user can delete a plant when multiple exist', function () {
    $org = Organization::create(['name' => 'Org One']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $plant2 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 2',
        'code' => 'P2',
        'slug' => 'plant-2',
        'is_default' => false,
    ]);

    $user = plantManager($org, $plant1);

    $response = $this->actingAs($user)->delete(route('plants.destroy', ['plant' => $plant2->id]));

    $response->assertRedirect();
    expect(Plant::find($plant2->id))->toBeNull();
});

test('soft deleted plant code and slug can be reused', function () {
    $org = Organization::create(['name' => 'Org One']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $archived = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Archived Plant',
        'code' => 'REUSE',
        'slug' => 'reuse-plant',
        'is_default' => false,
    ]);

    $user = plantManager($org, $plant1);

    $this->actingAs($user)->delete(route('plants.destroy', ['plant' => $archived->id]))->assertRedirect();

    $response = $this->actingAs($user)->post(route('plants.store'), [
        'name' => 'Reborn Plant',
        'code' => 'REUSE',
        'slug' => 'reuse-plant',
        'status' => 'Active',
        'is_default' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors(['code', 'slug']);

    expect(Plant::where('organization_id', $org->id)->where('code', 'REUSE')->whereNull('deleted_at')->exists())->toBeTrue();
});

test('user cannot delete the last plant', function () {
    $org = Organization::create(['name' => 'Org One']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $user = plantManager($org, $plant1);

    $response = $this->actingAs($user)->delete(route('plants.destroy', ['plant' => $plant1->id]));

    $response->assertSessionHasErrors('error');
    expect(Plant::find($plant1->id))->not->toBeNull();
});

test('user can assign a plant manager from active plant employees without a login', function () {
    $org = Organization::create(['name' => 'Org One']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $user = plantManager($org, $plant1);

    $manager = Employee::create([
        'employee_code' => 'MGR-P1',
        'first_name' => 'Bruno',
        'last_name' => 'Mcmahon',
        'email' => 'bruno@example.com',
        'organization_id' => $org->id,
        'plant_id' => $plant1->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'user_id' => null,
    ]);

    $response = $this->actingAs($user)->post(route('plants.store'), [
        'name' => 'Plant 3',
        'code' => 'P3',
        'slug' => 'plant-3',
        'status' => 'Active',
        'is_default' => false,
        'manager_id' => $manager->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors('manager_id');

    $created = Plant::where('organization_id', $org->id)->where('code', 'P3')->first();

    expect($created)->not->toBeNull()
        ->and($created->manager_id)->toBe($manager->id);
});

test('user can update a plant manager from active plant employees', function () {
    $org = Organization::create(['name' => 'Org One']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $user = plantManager($org, $plant1);

    $manager = Employee::create([
        'employee_code' => 'MGR-P2',
        'first_name' => 'Rhea',
        'last_name' => 'Meyers',
        'email' => 'rhea@example.com',
        'organization_id' => $org->id,
        'plant_id' => $plant1->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'user_id' => null,
    ]);

    $response = $this->actingAs($user)->put(route('plants.update', ['plant' => $plant1->id]), [
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'status' => 'Active',
        'is_default' => true,
        'manager_id' => $manager->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors('manager_id');

    expect($plant1->fresh()->manager_id)->toBe($manager->id);
});

test('plant manager must belong to the same organization', function () {
    $org = Organization::create(['name' => 'Org One']);
    $otherOrg = Organization::create(['name' => 'Org Two']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $otherPlant = Plant::create([
        'organization_id' => $otherOrg->id,
        'name' => 'Other Plant',
        'code' => 'OP1',
        'slug' => 'other-plant',
        'is_default' => true,
    ]);

    $user = plantManager($org, $plant1);

    $otherOrgManager = Employee::create([
        'employee_code' => 'MGR-X',
        'first_name' => 'Other',
        'last_name' => 'Org',
        'organization_id' => $otherOrg->id,
        'plant_id' => $otherPlant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $response = $this->actingAs($user)->post(route('plants.store'), [
        'name' => 'Plant 2',
        'code' => 'P2',
        'slug' => 'plant-2',
        'status' => 'Active',
        'is_default' => false,
        'manager_id' => $otherOrgManager->id,
    ]);

    $response->assertSessionHasErrors('manager_id');
});

test('plant manager search finds active plant employees without a login', function () {
    $org = Organization::create(['name' => 'Org One']);
    $otherOrg = Organization::create(['name' => 'Org Two']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $otherPlant = Plant::create([
        'organization_id' => $otherOrg->id,
        'name' => 'Other Plant',
        'code' => 'OP1',
        'slug' => 'other-plant',
        'is_default' => true,
    ]);

    $user = plantManager($org, $plant1);

    Employee::create([
        'employee_code' => 'EMP-BRU',
        'first_name' => 'Bruno',
        'last_name' => 'Mcmahon',
        'email' => 'bruno@example.com',
        'organization_id' => $org->id,
        'plant_id' => $plant1->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'user_id' => null,
    ]);

    Employee::create([
        'employee_code' => 'EMP-CAR',
        'first_name' => 'Carol',
        'last_name' => 'Other',
        'organization_id' => $otherOrg->id,
        'plant_id' => $otherPlant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $response = $this->actingAs($user)->getJson(route('organization.users.search', [
        'q' => 'bru',
        'with_employee' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1);
    $response->assertJsonFragment([
        'name' => 'Bruno Mcmahon',
        'email' => 'bruno@example.com',
    ]);
});

test('plant manager search returns empty until a query is typed', function () {
    $org = Organization::create(['name' => 'Org One']);

    $plant1 = Plant::create([
        'organization_id' => $org->id,
        'name' => 'Plant 1',
        'code' => 'P1',
        'slug' => 'plant-1',
        'is_default' => true,
    ]);

    $user = plantManager($org, $plant1);

    Employee::create([
        'employee_code' => 'EMP-HID',
        'first_name' => 'Hidden',
        'last_name' => 'Manager',
        'organization_id' => $org->id,
        'plant_id' => $plant1->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $response = $this->actingAs($user)->getJson(route('organization.users.search', [
        'with_employee' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertExactJson([]);
});
