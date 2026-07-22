<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index()
    {
        $roles = Role::with('permissions')
            ->whereNotIn('slug', ['super-admin', 'admin'])
            ->get();

        $permissions = Permission::whereNotIn('slug', [
            'super-admin-dashboard',
        ])->get();

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = Str::slug($validated['name']);
        $count = 1;
        while (Role::where('slug', $slug)->exists()) {
            $slug = Str::slug($validated['name']) . '-' . $count++;
        }

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
        ]);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role)
    {
        $user = $request->user();
        if ($role->slug === 'super-admin' && ! $user->hasRole('super-admin')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = $role->slug;
        if ($role->slug !== 'super-admin' && $role->slug !== 'admin') {
            $slug = Str::slug($validated['name']);
            $count = 1;
            while (Role::where('slug', $slug)->where('id', '!=', $role->id)->exists()) {
                $slug = Str::slug($validated['name']) . '-' . $count++;
            }
        }

        $role->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
        ]);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role)
    {
        if ($role->slug === 'super-admin' || $role->slug === 'admin') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Built-in administrator roles cannot be deleted.',
            ]);
            return redirect()->back();
        }

        $role->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role deleted successfully.',
        ]);

        return redirect()->back();
    }
}
