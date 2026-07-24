<?php

use App\Models\Department;
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

    $this->org = Organization::firstOrCreate(['name' => 'Profile Org']);
    $this->plant = Plant::firstOrCreate([
        'organization_id' => $this->org->id,
        'code' => 'PP1',
        'slug' => 'profile-plant',
    ], [
        'name' => 'Profile Plant',
    ]);

    $adminRole = Role::where('slug', 'admin')->first();

    $this->adminUser = User::create([
        'name' => 'Profile Admin',
        'email' => 'profile-admin@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->adminUser->roles()->sync([$adminRole->id]);

    $this->department = Department::factory()->create([
        'name' => 'Operations',
        'code' => 'OPS',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
    ]);
});

it('stores optional gender date of birth and address', function () {
    $response = $this->actingAs($this->adminUser)->post('/employees', [
        'employee_code' => 'EMP-PERS-01',
        'first_name' => 'John',
        'last_name' => 'Smith',
        'gender' => 'Male',
        'date_of_birth' => '1990-05-15',
        'address' => '12 Factory Road',
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'create_login' => false,
    ]);

    $response->assertRedirect();

    $employee = Employee::query()->where('employee_code', 'EMP-PERS-01')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->gender)->toBe('Male')
        ->and($employee->date_of_birth?->toDateString())->toBe('1990-05-15')
        ->and($employee->address)->toBe('12 Factory Road');
});

it('shows the employee dashboard profile with personal and employment fields', function () {
    $manager = Employee::create([
        'employee_code' => 'EMP-MGR-01',
        'first_name' => 'Alex',
        'last_name' => 'Lead',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    $employee = Employee::create([
        'employee_code' => 'EMP-00125',
        'first_name' => 'John',
        'last_name' => 'Smith',
        'job_title' => 'Operator',
        'gender' => 'Male',
        'date_of_birth' => '1992-01-10',
        'address' => '99 Plant Ave',
        'phone' => '555-0100',
        'email' => 'john.smith@example.com',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'department_id' => $this->department->id,
        'manager_id' => $manager->id,
        'employment_type' => 'Full-Time',
        'hire_date' => now()->subYears(3)->toDateString(),
        'status' => 'Active',
    ]);

    $response = $this->actingAs($this->adminUser)->get("/employees/{$employee->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('employees/profile')
        ->where('employee.employee_code', 'EMP-00125')
        ->where('employee.job_title', 'Operator')
        ->where('employee.gender', 'Male')
        ->where('employee.address', '99 Plant Ave')
        ->where('employee.department.name', 'Operations')
        ->where('employee.plant.name', 'Profile Plant')
        ->where('employee.manager.first_name', 'Alex')
        ->where('employee.years_of_service', 3)
        ->has('departments')
        ->has('shifts')
        ->has('employee.role')
    );
});

it('soft deletes employees without hard delete', function () {
    $employee = Employee::create([
        'employee_code' => 'EMP-ARCH-01',
        'first_name' => 'Archive',
        'last_name' => 'Me',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    expect($employee->hasOperationalHistory())->toBeFalse();

    $this->actingAs($this->adminUser)
        ->delete("/employees/{$employee->id}")
        ->assertRedirect();

    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
});

it('excludes inactive employees from assignment search', function () {
    Employee::create([
        'employee_code' => 'EMP-ACT-01',
        'first_name' => 'Active',
        'last_name' => 'Worker',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
    ]);

    Employee::create([
        'employee_code' => 'EMP-INA-01',
        'first_name' => 'Inactive',
        'last_name' => 'Worker',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Inactive',
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('organization.users.search', [
        'q' => 'Worker',
        'with_employee' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['name' => 'Active Worker']);
    $response->assertJsonMissing(['name' => 'Inactive Worker']);
});

it('blocks login for users linked to inactive employees', function () {
    $loginUser = User::factory()->create([
        'email' => 'inactive.op@example.com',
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);

    Employee::create([
        'employee_code' => 'EMP-LOGIN-01',
        'first_name' => 'Inactive',
        'last_name' => 'Operator',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Inactive',
        'user_id' => $loginUser->id,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'inactive.op@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(config('fortify.username'));
    $this->assertGuest();
});

it('allows login for users linked to active employees', function () {
    $loginUser = User::factory()->create([
        'email' => 'active.op@example.com',
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);

    Employee::create([
        'employee_code' => 'EMP-LOGIN-02',
        'first_name' => 'Active',
        'last_name' => 'Operator',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
        'employment_type' => 'Full-Time',
        'status' => 'Active',
        'user_id' => $loginUser->id,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'active.op@example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($loginUser);
    $response->assertRedirect();
});
