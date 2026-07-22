<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_seeder_creates_super_admin_and_admin_users_with_roles_and_permissions(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($admin);

        $this->assertTrue($superAdmin->hasRole('super-admin'));
        $this->assertTrue($superAdmin->hasPermission('super-admin-dashboard'));
        $this->assertTrue($superAdmin->hasPermission('admin-dashboard'));

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->hasPermission('admin-dashboard'));
        $this->assertFalse($admin->hasRole('super-admin'));
    }

    public function test_super_admin_can_access_super_admin_dashboard_at_admin_dashboard(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();

        $response = $this->actingAs($superAdmin)->get('/admin/dashboard');
        $response->assertStatus(200);

        // /dashboard redirects super-admin to /admin/dashboard
        $response = $this->actingAs($superAdmin)->get('/dashboard');
        $response->assertRedirect('/admin/dashboard');
    }

    public function test_admin_can_access_admin_dashboard_at_dashboard(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);

        // admin cannot access /admin/dashboard (requires super-admin-dashboard permission)
        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_manage_admin_users(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        $admin = User::where('email', 'admin@example.com')->first();

        // 1. Index
        $response = $this->actingAs($superAdmin)->get('/admin/users');
        $response->assertStatus(200);

        // 2. Store
        $response = $this->actingAs($superAdmin)->post('/admin/users', [
            'name' => 'New Admin User',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'newadmin@example.com']);

        $newAdmin = User::where('email', 'newadmin@example.com')->first();
        $this->assertTrue($newAdmin->hasRole('admin'));

        // 3. Update
        $response = $this->actingAs($superAdmin)->put("/admin/users/{$newAdmin->id}", [
            'name' => 'Updated Admin User',
            'email' => 'updatedadmin@example.com',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['name' => 'Updated Admin User']);

        // 4. Destroy
        $response = $this->actingAs($superAdmin)->delete("/admin/users/{$newAdmin->id}");
        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $newAdmin->id]);
    }

    public function test_regular_admin_cannot_access_users_management(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $response = $this->actingAs($admin)->get('/admin/users');
        $response->assertStatus(403);
    }
}



