<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the departments.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $query = Department::query()
            ->forActivePlant($user)
            ->with(['manager:id,name,email'])
            ->withCount([
                'employees' => fn ($q) => $q->where('plant_id', $user->active_plant_id),
                'workCenters' => fn ($q) => $q->where('plant_id', $user->active_plant_id),
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $allowedSorts = ['name', 'code', 'employees_count', 'work_centers_count', 'status', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts) ? $request->get('sort_by') : 'name';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        $departments = $query->paginate($perPage)->withQueryString();

        $plantDepartments = Department::query()->forActivePlant($user);

        $reports = [
            'active' => (clone $plantDepartments)->where('status', 'Active')->count(),
            'inactive' => (clone $plantDepartments)->where('status', 'Inactive')->count(),
        ];

        return Inertia::render('departments/index', [
            'departments' => $departments,
            'statuses' => Department::STATUSES,
            'reports' => $reports,
            'filters' => $request->only(['search', 'status', 'sort_by', 'sort_dir', 'per_page']),
        ]);
    }

    /**
     * Display the specified department.
     */
    public function show(Request $request, Department $department)
    {
        $user = $request->user();
        if (! $user || ! $this->departmentBelongsToActivePlant($user, $department)) {
            abort(403);
        }

        $department->load(['manager:id,name,email']);
        $department->loadCount([
            'employees' => fn ($q) => $q->where('plant_id', $user->active_plant_id),
            'workCenters' => fn ($q) => $q->where('plant_id', $user->active_plant_id),
            'machines' => fn ($q) => $q->where('plant_id', $user->active_plant_id),
        ]);

        return Inertia::render('departments/show', [
            'department' => $department,
        ]);
    }

    /**
     * Store a newly created department in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $this->validateDepartment($request, $user);

        Department::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'plant_id' => $user->active_plant_id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified department in storage.
     */
    public function update(Request $request, Department $department)
    {
        $user = $request->user();
        if (! $user || ! $this->departmentBelongsToActivePlant($user, $department)) {
            abort(403);
        }

        $validated = $this->validateDepartment($request, $user, $department);

        if (empty($validated['code'])) {
            unset($validated['code']);
        }

        $department->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified department from storage.
     */
    public function destroy(Request $request, Department $department)
    {
        $user = $request->user();
        if (! $user || ! $this->departmentBelongsToActivePlant($user, $department)) {
            abort(403);
        }

        $inUse = $department->employees()->where('plant_id', $user->active_plant_id)->exists()
            || $department->workCenters()->where('plant_id', $user->active_plant_id)->exists();

        if ($inUse) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This department is currently in use.',
            ]);

            return redirect()->back();
        }

        $department->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDepartment(Request $request, $user, ?Department $department = null): array
    {
        $request->merge([
            'manager_id' => $request->filled('manager_id') ? $request->input('manager_id') : null,
            'code' => $request->filled('code') ? strtoupper(trim((string) $request->input('code'))) : null,
        ]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at')
                    ->ignore($department?->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at')
                    ->ignore($department?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(Department::STATUSES)],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where('organization_id', $user->organization_id),
            ],
        ]);
    }

    private function departmentBelongsToActivePlant($user, Department $department): bool
    {
        return $department->organization_id === $user->organization_id
            && (int) $department->plant_id === (int) $user->active_plant_id;
    }
}
