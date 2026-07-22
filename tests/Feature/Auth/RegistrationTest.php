<?php

use Laravel\Fortify\Features;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = \App\Models\User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->organization)->not->toBeNull();
    expect($user->organization->name)->toBe('Test User Organization');
    expect($user->activePlant)->not->toBeNull();
    expect($user->activePlant->name)->toBe('Chicago Plant');
    expect($user->activePlant->is_default)->toBeTrue();
    expect($user->hasRole('admin'))->toBeTrue();
});