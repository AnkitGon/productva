<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'plant_name' => 'Chicago Plant',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('setup.index', absolute: false));

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->organization)->not->toBeNull();
    expect($user->organization->name)->toBe('Test User Organization');
    expect($user->organization->setup_completed_at)->toBeNull();
    expect($user->getting_started_prompted_at)->not->toBeNull();
    expect($user->activePlant)->not->toBeNull();
    expect($user->activePlant->name)->toBe('Chicago Plant');
    expect($user->activePlant->is_default)->toBeTrue();
    expect($user->hasRole('admin'))->toBeTrue();
    expect(Role::whereIn('slug', [
        'super-admin',
        'admin',
        'plant-manager',
        'production-manager',
        'warehouse-manager',
        'quality-manager',
        'maintenance-manager',
        'purchasing-manager',
        'sales-manager',
        'finance-manager',
        'viewer',
    ])->count())->toBe(11);
    expect(Role::whereIn('slug', [
        'inventory-manager',
        'operator',
        'maintenance-engineer',
        'procurement-manager',
    ])->count())->toBe(0);
});
