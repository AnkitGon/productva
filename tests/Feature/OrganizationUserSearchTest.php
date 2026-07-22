<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::firstOrCreate(['name' => 'Search Org']);
    $this->otherOrg = Organization::firstOrCreate(['name' => 'Other Org']);

    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'SP1',
        'slug' => 'search-plant',
    ], [
        'name' => 'Search Plant',
    ]);

    $this->otherPlant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'SP2',
        'slug' => 'other-search-plant',
    ], [
        'name' => 'Other Search Plant',
    ]);

    $adminRole = Role::where('slug', 'admin')->first();

    $this->adminUser = User::create([
        'name' => 'Org Admin',
        'email' => 'org-search-admin@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->adminUser->roles()->sync([$adminRole->id]);
});

it('returns matching users for the active organization and plant', function () {
    User::create([
        'name' => 'Alice Active',
        'email' => 'alice.active@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);

    User::create([
        'name' => 'Bob Other Plant',
        'email' => 'bob.otherplant@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->otherPlant->id,
    ]);

    User::create([
        'name' => 'Carol Other Org',
        'email' => 'carol.otherorg@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->otherOrg->id,
        'active_plant_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('organization.users.search', [
        'q' => 'Alice',
    ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1);
    $response->assertJsonFragment([
        'name' => 'Alice Active',
        'email' => 'alice.active@example.com',
    ]);
});

it('returns an empty list when no search query is provided', function () {
    User::create([
        'name' => 'Alice Active',
        'email' => 'alice.active@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('organization.users.search'));

    $response->assertSuccessful();
    $response->assertExactJson([]);
});

it('can filter to users that have an employee profile on the active plant', function () {
    $withEmployee = User::create([
        'name' => 'Dana Manager',
        'email' => 'dana.manager@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);

    User::create([
        'name' => 'Evan Login Only',
        'email' => 'evan.login@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);

    $employee = Employee::create([
        'employee_code' => 'MGR-01',
        'first_name' => 'Dana',
        'last_name' => 'Manager',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'user_id' => $withEmployee->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('organization.users.search', [
        'q' => 'Dana',
        'with_employee' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1);
    $response->assertJsonFragment([
        'id' => $withEmployee->id,
        'employee_id' => $employee->id,
        'email' => 'dana.manager@example.com',
    ]);
});

it('does not include the admin role in employee login role options', function () {
    $response = $this->actingAs($this->adminUser)->get('/employees');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('employees/index')
        ->has('roles')
        ->where('roles', fn ($roles) => collect($roles)->every(fn ($role) => ! in_array($role['slug'], ['admin', 'super-admin'], true)))
    );
});
