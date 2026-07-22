<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'plant_id' => $this->plant->id,
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
            ->has('departments')
        );
    }

    public function test_employees_index_only_shows_active_plant_records(): void
    {
        $otherPlant = Plant::create([
            'organization_id' => $this->org->id,
            'code' => 'P2',
            'slug' => 'other-plant',
            'name' => 'Other Plant',
        ]);

        Employee::create([
            'employee_code' => 'ACTIVE-01',
            'first_name' => 'Active',
            'last_name' => 'Plant',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
        ]);

        Employee::create([
            'employee_code' => 'OTHER-01',
            'first_name' => 'Other',
            'last_name' => 'Plant',
            'organization_id' => $this->org->id,
            'plant_id' => $otherPlant->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/employees');

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.employee_code', 'ACTIVE-01')
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

    public function test_admin_can_update_employee(): void
    {
        $employee = Employee::create([
            'employee_code' => 'UPD-01',
            'first_name' => 'Dana',
            'last_name' => 'Lee',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'department_id' => $this->department->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
            'job_title' => 'Engineer',
        ]);

        $response = $this->actingAs($this->adminUser)->put("/employees/{$employee->id}", [
            'employee_code' => 'UPD-01',
            'first_name' => 'Dana',
            'last_name' => 'Lee',
            'display_name' => 'D. Lee',
            'department_id' => $this->department->id,
            'job_title' => 'Senior Engineer',
            'email' => 'dana@example.com',
            'employment_type' => 'Full-Time',
            'status' => 'Active',
            'create_login' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'job_title' => 'Senior Engineer',
            'display_name' => 'D. Lee',
            'email' => 'dana@example.com',
        ]);
    }

    public function test_admin_can_upload_employee_photo(): void
    {
        Storage::fake('public');

        $photo = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'employee_code' => 'EMP-PHOTO-01',
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'department_id' => $this->department->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
            'create_login' => false,
            'photo' => $photo,
        ]);

        $response->assertRedirect();

        $employee = Employee::where('employee_code', 'EMP-PHOTO-01')->first();
        $this->assertNotNull($employee);
        $this->assertNotNull($employee->photo_path);
        Storage::disk('public')->assertExists($employee->photo_path);
        $this->assertSame('/storage/'.$employee->photo_path, $employee->photo_url);
    }

    public function test_employee_photo_must_not_exceed_two_megabytes(): void
    {
        Storage::fake('public');

        $photo = UploadedFile::fake()->image('large.jpg')->size(2049);

        $response = $this->actingAs($this->adminUser)->post('/employees', [
            'employee_code' => 'EMP-PHOTO-02',
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'department_id' => $this->department->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
            'create_login' => false,
            'photo' => $photo,
        ]);

        $response->assertSessionHasErrors([
            'photo' => 'The photo must be 2MB or smaller.',
        ]);
        $this->assertDatabaseMissing('employees', [
            'employee_code' => 'EMP-PHOTO-02',
        ]);
    }

    public function test_employee_profile_includes_shift_details(): void
    {
        $shift = Shift::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'code' => 'MORN',
            'name' => 'Morning Shift',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'break_minutes' => 60,
            'grace_in_minutes' => 10,
            'grace_out_minutes' => 5,
            'overnight' => false,
            'working_minutes' => 420,
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $employee = Employee::create([
            'employee_code' => 'SH-VIEW-01',
            'first_name' => 'Sam',
            'last_name' => 'Shift',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'department_id' => $this->department->id,
            'shift_id' => $shift->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)->get("/employees/{$employee->id}");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('employees/profile')
            ->where('employee.shift.id', $shift->id)
            ->where('employee.shift.code', 'MORN')
            ->where('employee.shift.name', 'Morning Shift')
            ->where('employee.shift.hours_label', '7h')
        );
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
