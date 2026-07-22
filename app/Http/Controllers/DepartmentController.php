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
        if (!$user || !$user->organization_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $query = Department::where('organization_id', $user->organization_id)
            ->withCount('employees');

        // Search
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // Sorting
        $allowedSorts = ['name', 'employees_count', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts) ? $request->get('sort_by') : 'created_at';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $departments = $query->paginate($perPage)->withQueryString();

        return Inertia::render('departments/index', [
            'departments' => $departments,
            'filters' => $request->only(['search', 'sort_by', 'sort_dir', 'per_page']),
        ]);
    }

    /**
     * Store a newly created department in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->organization_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where('organization_id', $user->organization_id),
            ],
        ]);

        Department::create([
            'name' => $validated['name'],
            'organization_id' => $user->organization_id,
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
        if (!$user || $department->organization_id !== $user->organization_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where('organization_id', $user->organization_id)->ignore($department->id),
            ],
        ]);

        $department->update([
            'name' => $validated['name'],
        ]);

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
        if (!$user || $department->organization_id !== $user->organization_id) {
            abort(403);
        }

        $department->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department archived successfully.',
        ]);

        return redirect()->back();
    }
}
