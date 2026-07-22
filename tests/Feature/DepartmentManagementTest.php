<?php

namespace Tests\Feature;

use App\Models\Department;
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
        ]);
    }

    public function test_department_name_must_be_unique_within_organization(): void
    {
        Department::create([
            'name' => 'Finance',
            'organization_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post('/departments', [
            'name' => 'Finance',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_department(): void
    {
        $dept = Department::create([
            'name' => 'HR',
            'organization_id' => $this->org->id,
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

    public function test_admin_can_delete_department(): void
    {
        $dept = Department::create([
            'name' => 'Logistics',
            'organization_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/departments/{$dept->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('departments', [
            'id' => $dept->id,
        ]);
    }
}
