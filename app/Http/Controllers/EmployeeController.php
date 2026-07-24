<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $query = Employee::query()
            ->forActivePlant($user)
            ->with(['department', 'role', 'shift', 'manager.user', 'user.roles']);

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
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
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

        $departments = Department::query()
            ->forActivePlant($user)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'status']);

        $shifts = Shift::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('start_time')
            ->get(['id', 'name', 'code', 'status', 'start_time', 'end_time', 'overnight', 'working_minutes']);

        // Roles for assignment (exclude system/admin roles)
        $roles = Role::whereNotIn('slug', ['super-admin', 'admin'])->get(['id', 'name', 'slug']);

        $editEmployee = null;
        if ($request->filled('edit')) {
            $editEmployee = Employee::query()
                ->forActivePlant($user)
                ->with(['department', 'role', 'shift', 'manager.user', 'user.roles'])
                ->find($request->integer('edit'));
        }

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'departments' => $departments,
            'shifts' => $shifts,
            'roles' => $roles,
            'editEmployee' => $editEmployee,
            'filters' => $request->only(['department_id', 'shift_id', 'employment_type', 'status', 'has_login', 'search', 'sort_by', 'sort_dir', 'per_page', 'edit']),
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $request->merge([
            'manager_id' => $request->filled('manager_id') ? $request->input('manager_id') : null,
            'department_id' => $request->filled('department_id') ? $request->input('department_id') : null,
            'shift_id' => $request->filled('shift_id') ? $request->input('shift_id') : null,
            'role_id' => $request->filled('role_id') ? $request->input('role_id') : null,
            'gender' => $request->filled('gender') ? $request->input('gender') : null,
            'date_of_birth' => $request->filled('date_of_birth') ? $request->input('date_of_birth') : null,
            'address' => $request->filled('address') ? $request->input('address') : null,
        ]);

        $validated = $request->validate([
            'employee_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(Employee::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at'),
            ],
            'role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->whereNotIn('slug', ['super-admin', 'admin'])),
            ],
            'shift_id' => [
                'nullable',
                Rule::exists('shifts', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at'),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'employment_type' => ['required', Rule::in(['Full-Time', 'Part-Time', 'Contract', 'Temporary', 'Intern'])],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Active', 'Inactive', 'On Leave', 'Terminated'])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // System access fields
            'create_login' => ['boolean'],
            'login_email' => ['required_if:create_login,true', 'nullable', 'email', 'unique:users,email'],
            'login_password' => ['required_if:create_login,true', 'nullable', 'string', 'min:8'],
        ], [
            'photo.image' => 'The photo must be an image file.',
            'photo.mimes' => 'The photo must be a JPG, PNG, or WebP image.',
            'photo.max' => 'The photo must be 2MB or smaller.',
        ]);

        $linkedUserId = null;
        if (! empty($validated['create_login'])) {
            $createdUser = User::create([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'email' => $validated['login_email'],
                'password' => Hash::make($validated['login_password']),
                'email_verified_at' => now(),
                'organization_id' => $user->organization_id,
                'active_plant_id' => $user->active_plant_id,
            ]);

            if (! empty($validated['role_id'])) {
                $createdUser->roles()->sync([$validated['role_id']]);
            }
            $linkedUserId = $createdUser->id;
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('employees', 'public');
        }

        $employee = Employee::create([
            ...collect($validated)->except(['photo', 'create_login', 'login_email', 'login_password'])->all(),
            'photo_path' => $photoPath,
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
        if (! $user || ! $this->employeeBelongsToActivePlant($user, $employee)) {
            abort(403);
        }

        $employee->load([
            'department',
            'role.permissions',
            'shift',
            'manager',
            'plant',
            'user.roles.permissions',
        ]);

        $departments = Department::query()
            ->forActivePlant($user)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'status']);

        $shifts = Shift::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('start_time')
            ->get(['id', 'name', 'code', 'status']);

        return Inertia::render('employees/profile', [
            'employee' => $employee,
            'departments' => $departments,
            'shifts' => $shifts,
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (! $user || ! $this->employeeBelongsToActivePlant($user, $employee)) {
            abort(403);
        }

        $request->merge([
            'manager_id' => $request->filled('manager_id') ? $request->input('manager_id') : null,
            'department_id' => $request->filled('department_id') ? $request->input('department_id') : null,
            'shift_id' => $request->filled('shift_id') ? $request->input('shift_id') : null,
            'role_id' => $request->filled('role_id') ? $request->input('role_id') : null,
            'gender' => $request->filled('gender') ? $request->input('gender') : null,
            'date_of_birth' => $request->filled('date_of_birth') ? $request->input('date_of_birth') : null,
            'address' => $request->filled('address') ? $request->input('address') : null,
        ]);

        $validated = $request->validate([
            'employee_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at')
                    ->ignore($employee->id),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(Employee::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(function ($query) use ($user, $employee) {
                    $query->where('organization_id', $user->organization_id)
                        ->where('plant_id', $user->active_plant_id)
                        ->whereNull('deleted_at')
                        ->where(function ($statusQuery) use ($employee) {
                            $statusQuery->where('status', 'Active');
                            if ($employee->department_id) {
                                $statusQuery->orWhere('id', $employee->department_id);
                            }
                        });
                }),
            ],
            'role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->whereNotIn('slug', ['super-admin', 'admin'])),
            ],
            'shift_id' => [
                'nullable',
                Rule::exists('shifts', 'id')->where(function ($query) use ($user, $employee) {
                    $query->where('organization_id', $user->organization_id)
                        ->where('plant_id', $user->active_plant_id)
                        ->whereNull('deleted_at')
                        ->where(function ($statusQuery) use ($employee) {
                            $statusQuery->where('status', 'Active');
                            if ($employee->shift_id) {
                                $statusQuery->orWhere('id', $employee->shift_id);
                            }
                        });
                }),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(function ($query) use ($user, $employee) {
                    $query->where('organization_id', $user->organization_id)
                        ->where('plant_id', $user->active_plant_id)
                        ->whereNull('deleted_at')
                        ->where(function ($statusQuery) use ($employee) {
                            $statusQuery->where('status', 'Active');
                            if ($employee->manager_id) {
                                $statusQuery->orWhere('id', $employee->manager_id);
                            }
                        });
                }),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'employment_type' => ['required', Rule::in(['Full-Time', 'Part-Time', 'Contract', 'Temporary', 'Intern'])],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Active', 'Inactive', 'On Leave', 'Terminated'])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],

            // System access fields
            'create_login' => ['boolean'],
            'login_email' => [
                'required_if:create_login,true',
                'nullable',
                'email',
                $employee->user_id
                    ? Rule::unique('users', 'email')->ignore($employee->user_id)
                    : 'unique:users,email',
            ],
            'login_password' => ['nullable', 'string', 'min:8'],
        ], [
            'photo.image' => 'The photo must be an image file.',
            'photo.mimes' => 'The photo must be a JPG, PNG, or WebP image.',
            'photo.max' => 'The photo must be 2MB or smaller.',
        ]);

        $linkedUserId = $employee->user_id;

        if (! empty($validated['create_login'])) {
            if ($employee->user) {
                // Update existing user details
                $updateData = [
                    'name' => "{$validated['first_name']} {$validated['last_name']}",
                    'email' => $validated['login_email'],
                ];
                if (! empty($validated['login_password'])) {
                    $updateData['password'] = Hash::make($validated['login_password']);
                }
                $employee->user->update($updateData);
                $employee->user->roles()->sync(
                    ! empty($validated['role_id']) ? [$validated['role_id']] : []
                );
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
                if (! empty($validated['role_id'])) {
                    $createdUser->roles()->sync([$validated['role_id']]);
                }
                $linkedUserId = $createdUser->id;
            }
        } elseif ($employee->user && array_key_exists('role_id', $validated)) {
            $employee->user->roles()->sync(
                ! empty($validated['role_id']) ? [$validated['role_id']] : []
            );
        }

        $photoPath = $employee->photo_path;
        if ($request->hasFile('photo')) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('employees', 'public');
        } elseif (! empty($validated['remove_photo'])) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
            $photoPath = null;
        }

        $updatePayload = collect($validated)->except(['photo', 'remove_photo', 'create_login', 'login_email', 'login_password'])->all();
        if (empty($updatePayload['employee_code'])) {
            unset($updatePayload['employee_code']);
        }

        $employee->update([
            ...$updatePayload,
            'photo_path' => $photoPath,
            'user_id' => $linkedUserId,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employee profile updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Perform a bulk action on selected employees.
     */
    public function bulk(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'assign_shift', 'assign_department', 'export', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'shift_id' => [
                'required_if:action,assign_shift',
                'nullable',
                Rule::exists('shifts', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at'),
            ],
            'department_id' => [
                'required_if:action,assign_department',
                'nullable',
                Rule::exists('departments', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at'),
            ],
        ]);

        $permission = match ($validated['action']) {
            'activate', 'deactivate', 'assign_shift', 'assign_department' => 'employees.update',
            'export' => 'employees.export',
            'delete' => 'employees.delete',
        };

        if (! $user->hasPermission($permission)) {
            abort(403);
        }

        $employees = Employee::query()
            ->forActivePlant($user)
            ->whereIn('id', $validated['ids'])
            ->get();

        if ($employees->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No matching employees found for this plant.',
            ]);

            return redirect()->back();
        }

        return match ($validated['action']) {
            'activate' => $this->bulkUpdateStatus($employees, 'Active', 'Employees activated successfully.'),
            'deactivate' => $this->bulkUpdateStatus($employees, 'Inactive', 'Employees deactivated successfully.'),
            'assign_shift' => $this->bulkAssignShift($employees, (int) $validated['shift_id']),
            'assign_department' => $this->bulkAssignDepartment($employees, (int) $validated['department_id']),
            'export' => $this->bulkExport($employees),
            'delete' => $this->bulkDelete($employees),
        };
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (! $user || ! $this->employeeBelongsToActivePlant($user, $employee)) {
            abort(403);
        }

        // Soft delete / archive only. Hard delete is blocked when operational history exists.
        if ($employee->hasOperationalHistory() && $request->boolean('force')) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Employees with production, attendance, or quality history cannot be permanently deleted.',
            ]);

            return redirect()->back();
        }

        $employee->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employee profile archived successfully.',
        ]);

        return redirect()->back();
    }

    private function bulkUpdateStatus($employees, string $status, string $message)
    {
        Employee::query()
            ->whereIn('id', $employees->pluck('id'))
            ->update(['status' => $status]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return redirect()->back();
    }

    private function bulkAssignShift($employees, int $shiftId)
    {
        Employee::query()
            ->whereIn('id', $employees->pluck('id'))
            ->update(['shift_id' => $shiftId]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Shift assigned to selected employees.',
        ]);

        return redirect()->back();
    }

    private function bulkAssignDepartment($employees, int $departmentId)
    {
        Employee::query()
            ->whereIn('id', $employees->pluck('id'))
            ->update(['department_id' => $departmentId]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department assigned to selected employees.',
        ]);

        return redirect()->back();
    }

    private function bulkDelete($employees)
    {
        Employee::query()
            ->whereIn('id', $employees->pluck('id'))
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Selected employees archived successfully.',
        ]);

        return redirect()->back();
    }

    private function bulkExport($employees)
    {
        $employees->loadMissing(['department', 'shift', 'role', 'manager']);

        $filename = 'employees-'.now()->format('Y-m-d-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($employees) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'employee_code',
                'first_name',
                'last_name',
                'display_name',
                'email',
                'phone',
                'mobile',
                'job_title',
                'department',
                'shift',
                'role',
                'manager',
                'employment_type',
                'hire_date',
                'status',
            ]);

            foreach ($employees as $employee) {
                fputcsv($handle, [
                    $employee->employee_code,
                    $employee->first_name,
                    $employee->last_name,
                    $employee->display_name,
                    $employee->email,
                    $employee->phone,
                    $employee->mobile,
                    $employee->job_title,
                    $employee->department?->name,
                    $employee->shift?->name,
                    $employee->role?->name,
                    $employee->manager?->name,
                    $employee->employment_type,
                    $employee->hire_date,
                    $employee->status,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function employeeBelongsToActivePlant(User $user, Employee $employee): bool
    {
        return $employee->organization_id === $user->organization_id
            && (int) $employee->plant_id === (int) $user->active_plant_id;
    }
}
