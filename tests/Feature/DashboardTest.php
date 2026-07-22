<?php

use App\Models\User;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $user->roles()->attach($adminRole);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});