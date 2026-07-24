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

    $this->org = Organization::firstOrCreate(['name' => 'Manager Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'MP1',
        'slug' => 'manager-plant',
    ], [
        'name' => 'Manager Plant',
    ]);

    $adminRole = Role::where('slug', 'admin')->first();

    $this->adminUser = User::create([
        'name' => 'Org Admin',
        'email' => 'manager-admin@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->adminUser->roles()->sync([$adminRole->id]);
});

it('lists plant employees without requiring a login account', function () {
    Employee::create([
        'employee_code' => 'MGR-01',
        'first_name' => 'Bruno',
        'last_name' => 'Mcmahon',
        'email' => 'bruno@example.com',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('organization.users.search', [
        'q' => 'Bruno',
        'with_employee' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'name' => 'Bruno Mcmahon',
        'email' => 'bruno@example.com',
        'label' => 'Bruno Mcmahon (MGR-01)',
    ]);
});

it('returns no employees until a search query is provided', function () {
    Employee::create([
        'employee_code' => 'MGR-00',
        'first_name' => 'Hidden',
        'last_name' => 'Person',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('organization.users.search', [
        'with_employee' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertExactJson([]);
});

it('can search plant employees by name without a login', function () {
    Employee::create([
        'employee_code' => 'MGR-02',
        'first_name' => 'Rhea',
        'last_name' => 'Meyers',
        'email' => 'rhea@example.com',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('organization.users.search', [
        'q' => 'Rhea',
        'with_employee' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1);
    $response->assertJsonFragment([
        'name' => 'Rhea Meyers',
        'email' => 'rhea@example.com',
        'label' => 'Rhea Meyers (MGR-02)',
    ]);
});

it('can assign a reporting manager by employee id without a login', function () {
    $manager = Employee::create([
        'employee_code' => 'MGR-03',
        'first_name' => 'Bob',
        'last_name' => 'Boss',
        'email' => 'bob.boss@example.com',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)->post('/employees', [
        'employee_code' => 'EMP-RM-01',
        'first_name' => 'Casey',
        'last_name' => 'Report',
        'department_id' => null,
        'role_id' => null,
        'shift_id' => null,
        'job_title' => 'Operator',
        'manager_id' => $manager->id,
        'email' => 'casey.report@example.com',
        'phone' => null,
        'mobile' => null,
        'employment_type' => 'Full-Time',
        'hire_date' => null,
        'status' => 'Active',
        'create_login' => false,
    ]);

    $response->assertRedirect();

    $employee = Employee::query()->where('employee_code', 'EMP-RM-01')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->manager_id)->toBe($manager->id)
        ->and($employee->manager->first_name)->toBe('Bob');
});

it('rejects a reporting manager from another plant', function () {
    $otherPlant = Plant::create([
        'organization_id' => $this->org->id,
        'code' => 'MP2',
        'slug' => 'other-manager-plant',
        'name' => 'Other Manager Plant',
    ]);

    $otherManager = Employee::create([
        'employee_code' => 'MGR-04',
        'first_name' => 'Dana',
        'last_name' => 'Elsewhere',
        'organization_id' => $this->org->id,
        'plant_id' => $otherPlant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $response = $this->actingAs($this->adminUser)->post('/employees', [
        'employee_code' => 'EMP-RM-02',
        'first_name' => 'Evan',
        'last_name' => 'Local',
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'manager_id' => $otherManager->id,
        'create_login' => false,
    ]);

    $response->assertSessionHasErrors('manager_id');
});
