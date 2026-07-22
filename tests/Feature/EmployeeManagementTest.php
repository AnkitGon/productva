<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Organization $org;
    protected Plant $plant;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::firstOrCreate(['name' => 'Test Org']);
        $this->plant = Plant::firstOrCreate([
            'organization_id' => $this->org->id,
            'code' => 'P1',
            'slug' => 'test-plant',
        ], [
            'name' => 'Test Plant',
        ]);

        $this->department = Department::create([
            'name' => 'Engineering',
            'organization_id' => $this->org->id,
        ]);

        $adminRole = Role::where('slug', 'admin')->first();
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'org-admin@example.com',
            'password' => bcrypt('password'),
            'organization_id' => $this->org->id,
            'active_plant_id' => $this->plant->id,
        ]);
        $this->adminUser->roles()->sync([$adminRole->id]);
    }

    public function test_admin_can_access_employees_directory(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/employees');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees')
            ->has('plants')
            ->has('departments')
        );
    }

    public function test_admin_can_create_employee_without_login(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'employee_code' => 'EMP-TEST-99',
            'first_name' => 'Billy',
            'last_name' => 'Bob',
            'department_id' => $this->department->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
            'create_login' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-TEST-99',
            'first_name' => 'Billy',
            'last_name' => 'Bob',
            'user_id' => null,
        ]);
    }

    public function test_admin_can_create_employee_with_system_login(): void
    {
        $operatorRole = Role::where('slug', 'operator')->first();

        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'employee_code' => 'EMP-OP-01',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'department_id' => $this->department->id,
            'employment_type' => 'Part-Time',
            'status' => 'Active',
            'create_login' => true,
            'login_email' => 'alice@productva.com',
            'login_role_id' => $operatorRole->id,
            'login_password' => 'password123',
        ]);

        $response->assertRedirect();
        
        $employee = Employee::where('employee_code', 'EMP-OP-01')->first();
        $this->assertNotNull($employee);
        $this->assertNotNull($employee->user_id);
        
        $this->assertDatabaseHas('users', [
            'id' => $employee->user_id,
            'email' => 'alice@productva.com',
        ]);
    }

    public function test_employee_code_must_be_unique_within_organization(): void
    {
        // Create first employee
        Employee::create([
            'employee_code' => 'DUP-100',
            'first_name' => 'Alice',
            'last_name' => 'One',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
        ]);

        // Attempt duplicate employee code creation
        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'employee_code' => 'DUP-100',
            'first_name' => 'Alice',
            'last_name' => 'Two',
            'employment_type' => 'Full-Time',
            'status' => 'Active',
            'create_login' => false,
        ]);

        $response->assertSessionHasErrors('employee_code');
    }

    public function test_employee_is_soft_deleted(): void
    {
        $employee = Employee::create([
            'employee_code' => 'DEL-01',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/employees/{$employee->id}");

        $response->assertRedirect();
        
        // Assert soft deleted
        $this->assertSoftDeleted('employees', [
            'id' => $employee->id,
        ]);
    }
}
