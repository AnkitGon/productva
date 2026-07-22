<?php

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
