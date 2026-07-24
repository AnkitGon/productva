<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkCenter;
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
            ->has('statuses')
            ->has('reports')
            ->has('reports.active')
            ->has('reports.inactive')
        );
    }

    public function test_admin_can_view_department_detail(): void
    {
        $dept = Department::factory()->create([
            'name' => 'Production',
            'code' => 'PROD',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        Employee::create([
            'employee_code' => 'EMP-SHOW-01',
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'department_id' => $dept->id,
            'employment_type' => 'Full-Time',
            'status' => 'Active',
        ]);

        WorkCenter::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'department_id' => $dept->id,
            'code' => 'WC-PROD',
            'name' => 'Production Cell',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)
            ->get("/departments/{$dept->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('departments/show')
                ->where('department.name', 'Production')
                ->where('department.employees_count', 1)
                ->where('department.work_centers_count', 1)
                ->where('department.machines_count', 0)
            );
    }

    public function test_department_reports_include_active_and_inactive_counts(): void
    {
        Department::factory()->create([
            'name' => 'Active Dept',
            'code' => 'ACT',
            'status' => 'Active',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        Department::factory()->create([
            'name' => 'Inactive Dept',
            'code' => 'INA',
            'status' => 'Inactive',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        $this->actingAs($this->adminUser)
            ->get('/departments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reports.active', 1)
                ->where('reports.inactive', 1)
            );
    }

    public function test_admin_can_create_department(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/departments', [
            'name' => 'Research & Development',
            'code' => 'rnd',
            'status' => 'Active',
            'description' => 'Product research team',
            'manager_id' => $this->adminUser->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'name' => 'Research & Development',
            'code' => 'RND',
            'status' => 'Active',
            'description' => 'Product research team',
            'manager_id' => $this->adminUser->id,
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);
    }

    public function test_department_name_must_be_unique_within_active_plant(): void
    {
        Department::factory()->create([
            'name' => 'Finance',
            'code' => 'FIN',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post('/departments', [
            'name' => 'Finance',
            'code' => 'FIN2',
            'status' => 'Active',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_department_code_must_be_unique_within_active_plant(): void
    {
        Department::factory()->create([
            'name' => 'Finance',
            'code' => 'FIN',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post('/departments', [
            'name' => 'Financial Planning',
            'code' => 'fin',
            'status' => 'Active',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_departments_index_only_shows_active_plant_records(): void
    {
        $otherPlant = Plant::create([
            'organization_id' => $this->org->id,
            'code' => 'P2',
            'slug' => 'other-plant',
            'name' => 'Other Plant',
        ]);

        Department::factory()->create([
            'name' => 'Active Dept',
            'code' => 'ACT',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        Department::factory()->create([
            'name' => 'Other Dept',
            'code' => 'OTH',
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
        $dept = Department::factory()->create([
            'name' => 'HR',
            'code' => 'HR',
            'status' => 'Active',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->actingAs($this->adminUser)->put("/departments/{$dept->id}", [
            'name' => 'Human Resources',
            'code' => 'HRES',
            'status' => 'Inactive',
            'description' => 'People operations',
            'manager_id' => '',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'name' => 'Human Resources',
            'code' => 'HRES',
            'status' => 'Inactive',
            'description' => 'People operations',
            'manager_id' => null,
        ]);
    }

    public function test_admin_can_delete_department_without_employees(): void
    {
        $dept = Department::factory()->create([
            'name' => 'Logistics',
            'code' => 'LOG',
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
        $dept = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
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

    public function test_admin_cannot_delete_department_with_work_centers(): void
    {
        $dept = Department::factory()->create([
            'name' => 'Assembly',
            'code' => 'ASM',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        WorkCenter::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'department_id' => $dept->id,
            'code' => 'WC-ASM',
            'name' => 'Assembly Cell',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/departments/{$dept->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'deleted_at' => null,
        ]);
    }

    public function test_departments_index_can_filter_by_status(): void
    {
        Department::factory()->create([
            'name' => 'Active Dept',
            'code' => 'ACT',
            'status' => 'Active',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        Department::factory()->create([
            'name' => 'Inactive Dept',
            'code' => 'INA',
            'status' => 'Inactive',
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
        ]);

        $this->actingAs($this->adminUser)
            ->get('/departments?status=Inactive')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('departments/index')
                ->has('departments.data', 1)
                ->where('departments.data.0.name', 'Inactive Dept')
            );
    }

    public function test_same_department_name_allowed_across_plants(): void
    {
        $otherPlant = Plant::create([
            'organization_id' => $this->org->id,
            'code' => 'P3',
            'slug' => 'plant-three',
            'name' => 'Plant Three',
        ]);

        Department::factory()->create([
            'name' => 'Production',
            'code' => 'PROD',
            'organization_id' => $this->org->id,
            'plant_id' => $otherPlant->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post('/departments', [
            'name' => 'Production',
            'code' => 'PROD',
            'status' => 'Active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'name' => 'Production',
            'code' => 'PROD',
            'plant_id' => $this->plant->id,
        ]);
    }
}
