<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_access_roles_management_index(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();

        $response = $this->actingAs($superAdmin)->get(route('admin.roles.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/roles/index')
            ->has('roles')
            ->has('permissions')
        );
    }

    public function test_super_admin_can_create_role_with_permissions(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        $perm1 = Permission::where('slug', 'plants.view')->first();
        $perm2 = Permission::where('slug', 'plants.create')->first();

        $response = $this->actingAs($superAdmin)->post(route('admin.roles.store'), [
            'name' => 'Custom Staff Role',
            'description' => 'Test description',
            'permissions' => [$perm1->id, $perm2->id],
        ]);

        $response->assertRedirect();

        $role = Role::where('slug', 'custom-staff-role')->first();
        $this->assertNotNull($role);
        $this->assertEquals('Custom Staff Role', $role->name);
        $this->assertEquals('Test description', $role->description);
        $this->assertTrue($role->permissions->contains('id', $perm1->id));
        $this->assertTrue($role->permissions->contains('id', $perm2->id));
    }

    public function test_super_admin_can_update_role(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        $role = Role::create([
            'name' => 'Old Role',
            'slug' => 'old-role',
            'description' => 'Old description',
        ]);

        $perm = Permission::where('slug', 'plants.view')->first();

        $response = $this->actingAs($superAdmin)->put(route('admin.roles.update', ['role' => $role->id]), [
            'name' => 'New Role Name',
            'description' => 'New description details',
            'permissions' => [$perm->id],
        ]);

        $response->assertRedirect();

        $updated = $role->fresh();
        $this->assertEquals('New Role Name', $updated->name);
        $this->assertEquals('New description details', $updated->description);
        $this->assertTrue($updated->permissions->contains('id', $perm->id));
    }

    public function test_super_admin_cannot_delete_built_in_roles(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        $response = $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', ['role' => $adminRole->id]));

        $response->assertRedirect();
        $this->assertNotNull($adminRole->fresh());
    }

    public function test_super_admin_can_delete_custom_role(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        $role = Role::create([
            'name' => 'Delete Custom Role',
            'slug' => 'delete-custom-role',
        ]);

        $response = $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', ['role' => $role->id]));

        $response->assertRedirect();
        $this->assertNull(Role::find($role->id));
    }

    public function test_non_admin_cannot_access_roles_management(): void
    {
        $org = Organization::create(['name' => 'Org One']);
        $viewerRole = Role::where('slug', 'viewer')->first();

        $nonAdmin = User::create([
            'name' => 'Regular Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
        ]);
        $nonAdmin->roles()->sync([$viewerRole->id]);

        $response = $this->actingAs($nonAdmin)->get(route('admin.roles.index'));
        $response->assertStatus(403);
    }
}
