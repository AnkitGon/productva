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

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Organization $org;

    protected Plant $plant;

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

    public function test_admin_can_access_departments_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/departments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('departments/index')
            ->has('departments')
        );
    }

    public function test_admin_can_create_department(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/departments', [
            'name' => 'Research & Development',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'name' => 'Research & Development',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);
    }

    public function test_department_name_must_be_unique_within_active_plant(): void
    {
        Department::create([
            'name' => 'Finance',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post('/departments', [
            'name' => 'Finance',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_departments_index_only_shows_active_plant_records(): void
    {
        $otherPlant = Plant::create([
            'organization_id' => $this->org->id,
            'code' => 'P2',
            'slug' => 'other-plant',
            'name' => 'Other Plant',
        ]);

        Department::create([
            'name' => 'Active Dept',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        Department::create([
            'name' => 'Other Dept',
            'organization_id' => $this->org->id,
            'plant_id' => $otherPlant->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/departments');

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('departments/index')
            ->has('departments.data', 1)
            ->where('departments.data.0.name', 'Active Dept')
        );
    }

    public function test_admin_can_update_department(): void
    {
        $dept = Department::create([
            'name' => 'HR',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->actingAs($this->adminUser)->put("/departments/{$dept->id}", [
            'name' => 'Human Resources',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'name' => 'Human Resources',
        ]);
    }

    public function test_admin_can_delete_department_without_employees(): void
    {
        $dept = Department::create([
            'name' => 'Logistics',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/departments/{$dept->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('departments', [
            'id' => $dept->id,
        ]);
    }

    public function test_admin_cannot_delete_department_with_employees(): void
    {
        $dept = Department::create([
            'name' => 'Engineering',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        Employee::create([
            'employee_code' => 'EMP-DEPT-01',
            'first_name' => 'Sam',
            'last_name' => 'Taylor',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'department_id' => $dept->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/departments/{$dept->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'deleted_at' => null,
        ]);
    }
}
