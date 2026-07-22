<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of admin users.
     */
    public function index()
    {
        $users = User::whereHas('roles', function ($query) {
            $query->where('slug', 'admin');
        })->with('roles')->latest()->paginate(10);

        return inertia('admin/users/index', [
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created admin user in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole->id);
        }

        return redirect()->back()->with('success', 'Admin user created successfully.');
    }

    /**
     * Update the specified admin user in storage.
     */
    public function update(Request $request, User $user)
    {
        // Ensure only users with admin role can be updated here
        if (! $user->hasRole('admin')) {
            abort(403, 'Only admin users can be updated from this panel.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->back()->with('success', 'Admin user updated successfully.');
    }

    /**
     * Remove the specified admin user from storage.
     */
    public function destroy(User $user)
    {
        if (! $user->hasRole('admin')) {
            abort(403, 'Only admin users can be deleted from this panel.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'Admin user deleted successfully.');
    }
}
