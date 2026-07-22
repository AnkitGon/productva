<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\WorkCenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WorkCenterController extends Controller
{
    /**
     * Display a listing of work centers for the active plant.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = WorkCenter::query()
            ->forActivePlant($user)
            ->with([
                'department:id,name',
                'supervisor:id,first_name,last_name,display_name,employee_code,user_id',
                'supervisor.user:id,name,email',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $allowedSorts = ['code', 'name', 'status', 'capacity', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'name';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        $departments = Department::query()
            ->forActivePlant($user)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('work-centers/index', [
            'workCenters' => $query->paginate($perPage)->withQueryString(),
            'departments' => $departments,
            'filters' => $request->only(['search', 'status', 'department_id', 'sort_by', 'sort_dir', 'per_page']),
        ]);
    }

    /**
     * Store a newly created work center for the active plant.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $this->validateWorkCenter($request, $user);

        WorkCenter::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'plant_id' => $user->active_plant_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Work center created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified work center.
     */
    public function update(Request $request, WorkCenter $workCenter)
    {
        $user = $request->user();
        if (! $user || ! $this->workCenterBelongsToActivePlant($user, $workCenter)) {
            abort(403);
        }

        $validated = $this->validateWorkCenter($request, $user, $workCenter);

        $workCenter->update([
            ...$validated,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Work center updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Archive (soft delete) the specified work center.
     */
    public function destroy(Request $request, WorkCenter $workCenter)
    {
        $user = $request->user();
        if (! $user || ! $this->workCenterBelongsToActivePlant($user, $workCenter)) {
            abort(403);
        }

        if ($workCenter->hasAssignedMachines()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot archive this work center while machines are still assigned. Reassign them first.',
            ]);

            return redirect()->back();
        }

        $workCenter->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Work center archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWorkCenter(Request $request, $user, ?WorkCenter $workCenter = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'supervisor_employee_id' => $request->filled('supervisor_employee_id')
                ? $request->input('supervisor_employee_id')
                : null,
            'capacity' => $request->filled('capacity') ? $request->input('capacity') : null,
            'capacity_uom' => $request->filled('capacity_uom') ? $request->input('capacity_uom') : null,
            'description' => $request->filled('description') ? $request->input('description') : null,
        ]);

        $uniqueCode = Rule::unique('work_centers', 'code')
            ->where(fn ($query) => $query
                ->where('plant_id', $user->active_plant_id)
                ->whereNull('deleted_at'));

        if ($workCenter) {
            $uniqueCode->ignore($workCenter->id);
        }

        return $request->validate([
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id),
            ],
            'code' => ['required', 'string', 'max:20', $uniqueCode],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'supervisor_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_uom' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function workCenterBelongsToActivePlant($user, WorkCenter $workCenter): bool
    {
        return (int) $workCenter->organization_id === (int) $user->organization_id
            && (int) $workCenter->plant_id === (int) $user->active_plant_id;
    }
}
