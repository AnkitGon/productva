<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('shared auth includes permission slugs for the authenticated user', function () {
    $user = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $user->roles()->attach($adminRole);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auth.user.permissions')
            ->where('auth.user.permissions', fn ($permissions) => collect($permissions)->contains('employees.view')
                && collect($permissions)->contains('departments.view')
                && collect($permissions)->contains('roles.view'))
        );
});

test('shared auth only includes permissions assigned to the user roles', function () {
    $permission = Permission::where('slug', 'admin-dashboard')->firstOrFail();
    $role = Role::create([
        'name' => 'Limited Viewer',
        'slug' => 'limited-viewer',
        'description' => 'Dashboard only',
    ]);
    $role->permissions()->sync([$permission->id]);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.permissions', ['admin-dashboard'])
        );
});
