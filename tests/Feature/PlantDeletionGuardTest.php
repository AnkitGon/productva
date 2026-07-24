<?php

use App\Models\Department;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use App\Support\DefaultRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create an organization, two plants, and an admin user.
 * Returns [$user, $org, $keepPlant, $targetPlant].
 */
function setupOrgWithTwoPlants(): array
{
    DefaultRoles::ensure();

    $org = Organization::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();

    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email_verified_at' => now(),
    ]);
    $user->roles()->attach($adminRole->id);

    $keepPlant = Plant::factory()->create([
        'organization_id' => $org->id,
        'status' => 'Active',
    ]);

    $targetPlant = Plant::factory()->create([
        'organization_id' => $org->id,
        'status' => 'Active',
    ]);

    $user->update(['active_plant_id' => $keepPlant->id]);

    return [$user, $org, $keepPlant, $targetPlant];
}

/**
 * Insert the minimal row needed for a child entity that has non-nullable FKs,
 * bypassing Eloquent factories to avoid constraint complexity.
 */
function insertRawWarehouse(int $orgId, int $plantId): void
{
    // We need warehouse_type_id — create a type first
    $typeId = DB::table('warehouse_types')->insertGetId([
        'organization_id' => $orgId,
        'code' => 'WT-TEST-'.rand(1000, 9999),
        'name' => 'Test Type',
        'status' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'name' => 'Temp User '.rand(1, 99999),
        'email' => 'tmp'.rand().'@test.com',
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('warehouses')->insert([
        'organization_id' => $orgId,
        'plant_id' => $plantId,
        'warehouse_type_id' => $typeId,
        'code' => 'WH-'.rand(1000, 9999),
        'name' => 'Test WH',
        'status' => 'Active',
        'allow_negative_stock' => 0,
        'is_default' => 0,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Unit: Plant::hasBlockingDependencies()
// ─────────────────────────────────────────────────────────────────────────────

describe('Plant::hasBlockingDependencies()', function () {
    it('returns false for a completely empty plant', function () {
        $org = Organization::factory()->create();
        $plant = Plant::factory()->create(['organization_id' => $org->id]);

        expect($plant->hasBlockingDependencies())->toBeFalse();
    });

    it('returns true when the plant has a warehouse', function () {
        $org = Organization::factory()->create();
        $plant = Plant::factory()->create(['organization_id' => $org->id]);

        insertRawWarehouse($org->id, $plant->id);

        expect($plant->hasBlockingDependencies())->toBeTrue();
    });

    it('returns true when the plant has a department', function () {
        $org = Organization::factory()->create();
        $plant = Plant::factory()->create(['organization_id' => $org->id]);

        Department::factory()->create([
            'plant_id' => $plant->id,
            'organization_id' => $org->id,
        ]);

        expect($plant->hasBlockingDependencies())->toBeTrue();
    });

    it('returns true when a user has this plant as their active_plant_id', function () {
        $org = Organization::factory()->create();
        $plant = Plant::factory()->create(['organization_id' => $org->id]);

        User::factory()->create([
            'organization_id' => $org->id,
            'active_plant_id' => $plant->id,
        ]);

        expect($plant->hasBlockingDependencies())->toBeTrue();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Feature: DELETE /plants/{plant}
// ─────────────────────────────────────────────────────────────────────────────

describe('DELETE /plants/{plant}', function () {
    it('soft-deletes an empty plant with no dependencies', function () {
        [$user, $org, $keepPlant, $targetPlant] = setupOrgWithTwoPlants();

        actingAs($user)
            ->delete(route('plants.destroy', $targetPlant))
            ->assertRedirect();

        // The plant must now be soft-deleted
        expect(Plant::withTrashed()->find($targetPlant->id)->deleted_at)->not->toBeNull();
    });

    it('blocks deletion when the plant has a warehouse', function () {
        [$user, $org, $keepPlant, $targetPlant] = setupOrgWithTwoPlants();

        insertRawWarehouse($org->id, $targetPlant->id);

        actingAs($user)
            ->delete(route('plants.destroy', $targetPlant))
            ->assertRedirect();

        // Plant must still exist (not soft-deleted)
        expect(Plant::find($targetPlant->id))->not->toBeNull();
    });

    it('blocks deletion when the plant has departments', function () {
        [$user, $org, $keepPlant, $targetPlant] = setupOrgWithTwoPlants();

        Department::factory()->create([
            'plant_id' => $targetPlant->id,
            'organization_id' => $org->id,
        ]);

        actingAs($user)
            ->delete(route('plants.destroy', $targetPlant))
            ->assertRedirect();

        expect(Plant::find($targetPlant->id))->not->toBeNull();
    });

    it('blocks deletion when the plant is the last plant in the organization', function () {
        DefaultRoles::ensure();
        $org = Organization::factory()->create();
        $adminRole = Role::where('slug', 'admin')->first();
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($adminRole->id);
        $plant = Plant::factory()->create(['organization_id' => $org->id]);
        $user->update(['active_plant_id' => $plant->id]);

        actingAs($user)
            ->delete(route('plants.destroy', $plant))
            ->assertRedirect();

        expect(Plant::find($plant->id))->not->toBeNull();
    });

    it('returns 403 when user tries to delete a plant from another organization', function () {
        DefaultRoles::ensure();

        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $adminRole = Role::where('slug', 'admin')->first();

        $user = User::factory()->create([
            'organization_id' => $org1->id,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($adminRole->id);

        // Plant belongs to org2
        $plant = Plant::factory()->create(['organization_id' => $org2->id]);

        actingAs($user)
            ->delete(route('plants.destroy', $plant))
            ->assertForbidden();
    });

    it('does NOT allow unauthenticated deletion', function () {
        $org = Organization::factory()->create();
        $plant = Plant::factory()->create(['organization_id' => $org->id]);

        $this->delete(route('plants.destroy', $plant))
            ->assertRedirect('/login');
    });
});
