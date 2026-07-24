<?php

use App\Models\Department;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'name' => 'Roadmap Org',
    ]);

    $this->plant = Plant::create([
        'organization_id' => $this->org->id,
        'code' => 'RM1',
        'slug' => 'roadmap-plant',
        'name' => 'Roadmap Plant',
        'status' => 'Active',
        'is_default' => true,
    ]);

    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $this->admin = User::factory()->create([
        'organization_id' => $this->org->id,
        'active_plant_id' => $this->plant->id,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);
});

test('dashboard stays a real dashboard page', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->missing('setupProgress'));
});

test('getting started is available as its own page with sidebar flag', function () {
    $this->actingAs($this->admin)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/index')
            ->where('showGettingStarted', true)
            ->has('roadmap.required', 8)
            ->has('roadmap.optional')
            ->has('roadmap.problems')
            ->has('roadmap.factory_health')
            ->where('roadmap.required.0.key', 'plants')
            ->where('roadmap.required.0.done', true)
            ->where('roadmap.next_required.title', 'Departments'));
});

test('configuring departments improves readiness and updates next step', function () {
    Department::factory()->create([
        'name' => 'Production',
        'code' => 'PROD',
        'organization_id' => $this->org->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roadmap.required.1.key', 'departments')
            ->where('roadmap.required.1.done', true)
            ->where('roadmap.required.1.metric', '1')
            ->where('roadmap.next_required.title', 'Employees'));
});

test('products without finished goods stay incomplete', function () {
    $this->actingAs($this->admin)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roadmap.required.3.key', 'products')
            ->where('roadmap.required.3.done', false)
            ->where('roadmap.problems', fn ($problems) => collect($problems)->contains(
                fn ($problem) => str_contains($problem['severity'], 'finished products')
            )));
});

test('marking getting started complete removes sidebar entry', function () {
    $this->actingAs($this->admin)
        ->post(route('setup.dismiss'))
        ->assertRedirect(route('dashboard'));

    expect($this->org->fresh()->setup_completed_at)->not->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('showGettingStarted', false));
});

test('first login opens getting started then later logins open dashboard', function () {
    expect($this->admin->getting_started_prompted_at)->toBeNull();

    $this->post(route('login.store'), [
        'email' => $this->admin->email,
        'password' => 'password',
    ])->assertRedirect(route('setup.index'));

    expect($this->admin->fresh()->getting_started_prompted_at)->not->toBeNull();

    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => $this->admin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});
