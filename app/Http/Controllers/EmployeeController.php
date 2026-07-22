<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{


    /**
     * Display a listing of the employees.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->organization_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $query = Employee::where('organization_id', $user->organization_id)
            ->with(['plant', 'department', 'manager', 'user.roles']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->filled('plant_id')) {
            $query->where('plant_id', $request->plant_id);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->employment_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('has_login')) {
            if ($request->has_login === 'yes') {
                $query->whereNotNull('user_id');
            } else {
                $query->whereNull('user_id');
            }
        }

        // Sorting
        $allowedSorts = ['employee_code', 'first_name', 'last_name', 'status', 'employment_type', 'hire_date', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts) ? $request->get('sort_by') : 'created_at';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $employees = $query->paginate($perPage)->withQueryString();

        $plants = Plant::where('organization_id', $user->organization_id)->get(['id', 'name']);
        $departments = Department::where('organization_id', $user->organization_id)->get(['id', 'name']);
        $managers = Employee::where('organization_id', $user->organization_id)->get(['id', 'first_name', 'last_name']);

        // Load roles for login assignment (excluding super-admin)
        $roles = Role::where('slug', '!=', 'super-admin')->get(['id', 'name', 'slug']);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'plants' => $plants,
            'departments' => $departments,
            'managers' => $managers,
            'roles' => $roles,
            'filters' => $request->only(['plant_id', 'department_id', 'employment_type', 'status', 'has_login', 'search', 'sort_by', 'sort_dir', 'per_page']),
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->organization_id) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees')->where('organization_id', $user->organization_id),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['required', Rule::in(['Full-Time', 'Part-Time', 'Contract', 'Temporary', 'Intern'])],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Active', 'Inactive', 'On Leave', 'Terminated'])],
            
            // System access fields
            'create_login' => ['boolean'],
            'login_email' => ['required_if:create_login,true', 'nullable', 'email', 'unique:users,email'],
            'login_role_id' => ['required_if:create_login,true', 'nullable', 'exists:roles,id'],
            'login_password' => ['required_if:create_login,true', 'nullable', 'string', 'min:8'],
        ]);

        $linkedUserId = null;
        if (!empty($validated['create_login'])) {
            $createdUser = User::create([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'email' => $validated['login_email'],
                'password' => Hash::make($validated['login_password']),
                'email_verified_at' => now(),
                'organization_id' => $user->organization_id,
                'active_plant_id' => $user->active_plant_id,
            ]);

            $createdUser->roles()->sync([$validated['login_role_id']]);
            $linkedUserId = $createdUser->id;
        }

        $employee = Employee::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'plant_id' => $user->active_plant_id,
            'user_id' => $linkedUserId,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employee profile created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Display the specified employee profile.
     */
    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (!$user || $employee->organization_id !== $user->organization_id) {
            abort(403);
        }

        $employee->load(['plant', 'department', 'manager', 'user.roles']);

        return Inertia::render('employees/profile', [
            'employee' => $employee,
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (!$user || $employee->organization_id !== $user->organization_id) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees')->where('organization_id', $user->organization_id)->ignore($employee->id),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['required', Rule::in(['Full-Time', 'Part-Time', 'Contract', 'Temporary', 'Intern'])],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Active', 'Inactive', 'On Leave', 'Terminated'])],

            // System access fields
            'create_login' => ['boolean'],
            'login_email' => [
                'required_if:create_login,true',
                'nullable',
                'email',
                $employee->user_id 
                    ? Rule::unique('users', 'email')->ignore($employee->user_id)
                    : 'unique:users,email'
            ],
            'login_role_id' => ['required_if:create_login,true', 'nullable', 'exists:roles,id'],
            'login_password' => ['nullable', 'string', 'min:8'],
        ]);

        $linkedUserId = $employee->user_id;

        if (!empty($validated['create_login'])) {
            if ($employee->user) {
                // Update existing user details
                $updateData = [
                    'name' => "{$validated['first_name']} {$validated['last_name']}",
                    'email' => $validated['login_email'],
                ];
                if (!empty($validated['login_password'])) {
                    $updateData['password'] = Hash::make($validated['login_password']);
                }
                $employee->user->update($updateData);
                $employee->user->roles()->sync([$validated['login_role_id']]);
            } else {
                // Create user profile
                $createdUser = User::create([
                    'name' => "{$validated['first_name']} {$validated['last_name']}",
                    'email' => $validated['login_email'],
                    'password' => Hash::make($validated['login_password'] ?? 'password123'),
                    'email_verified_at' => now(),
                    'organization_id' => $user->organization_id,
                    'active_plant_id' => $user->active_plant_id,
                ]);
                $createdUser->roles()->sync([$validated['login_role_id']]);
                $linkedUserId = $createdUser->id;
            }
        }

        $employee->update([
            ...$validated,
            'user_id' => $linkedUserId,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employee profile updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (!$user || $employee->organization_id !== $user->organization_id) {
            abort(403);
        }

        // Business Rule: Soft Delete only
        $employee->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employee profile archived successfully.',
        ]);

        return redirect()->back();
    }
}
