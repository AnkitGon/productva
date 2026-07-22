<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Machine;
use App\Models\WorkCenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MachineController extends Controller
{
    /**
     * Display a listing of machines for the active plant.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = Machine::query()
            ->forActivePlant($user)
            ->with([
                'department:id,name',
                'workCenter:id,name,code,department_id',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('manufacturer', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('work_center_id')) {
            $query->where('work_center_id', $request->work_center_id);
        }

        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', $request->manufacturer);
        }

        $allowedSorts = ['code', 'name', 'manufacturer', 'model', 'status', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'name';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        $departments = Department::query()
            ->forActivePlant($user)
            ->orderBy('name')
            ->get(['id', 'name']);

        $workCenters = WorkCenter::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'department_id']);

        $manufacturers = Machine::query()
            ->forActivePlant($user)
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer')
            ->values();

        return Inertia::render('machines/index', [
            'machines' => $query->paginate($perPage)->withQueryString(),
            'departments' => $departments,
            'workCenters' => $workCenters,
            'manufacturers' => $manufacturers,
            'statuses' => Machine::STATUSES,
            'filters' => $request->only([
                'search',
                'status',
                'department_id',
                'work_center_id',
                'manufacturer',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        ]);
    }

    /**
     * Store a newly created machine for the active plant.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $this->validateMachine($request, $user);
        $workCenter = WorkCenter::query()->findOrFail($validated['work_center_id']);

        Machine::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'plant_id' => $user->active_plant_id,
            'department_id' => $workCenter->department_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Machine created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified machine.
     */
    public function update(Request $request, Machine $machine)
    {
        $user = $request->user();
        if (! $user || ! $this->machineBelongsToActivePlant($user, $machine)) {
            abort(403);
        }

        $validated = $this->validateMachine($request, $user, $machine);
        $workCenter = WorkCenter::query()->findOrFail($validated['work_center_id']);

        $machine->update([
            ...$validated,
            'department_id' => $workCenter->department_id,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Machine updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Archive (soft delete) the specified machine.
     */
    public function destroy(Request $request, Machine $machine)
    {
        $user = $request->user();
        if (! $user || ! $this->machineBelongsToActivePlant($user, $machine)) {
            abort(403);
        }

        if ($machine->hasProductionHistory()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot archive this machine while it has production history.',
            ]);

            return redirect()->back();
        }

        $machine->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Machine archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMachine(Request $request, $user, ?Machine $machine = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'manufacturer' => $request->filled('manufacturer') ? $request->input('manufacturer') : null,
            'model' => $request->filled('model') ? $request->input('model') : null,
            'serial_number' => $request->filled('serial_number') ? trim((string) $request->input('serial_number')) : null,
            'asset_tag' => $request->filled('asset_tag') ? $request->input('asset_tag') : null,
            'installation_date' => $request->filled('installation_date') ? $request->input('installation_date') : null,
            'purchase_date' => $request->filled('purchase_date') ? $request->input('purchase_date') : null,
            'capacity' => $request->filled('capacity') ? $request->input('capacity') : null,
            'capacity_unit' => $request->filled('capacity_unit') ? $request->input('capacity_unit') : null,
            'notes' => $request->filled('notes') ? $request->input('notes') : null,
        ]);

        $uniqueCode = Rule::unique('machines', 'code')
            ->where(fn ($query) => $query
                ->where('plant_id', $user->active_plant_id)
                ->whereNull('deleted_at'));

        if ($machine) {
            $uniqueCode->ignore($machine->id);
        }

        $uniqueSerial = Rule::unique('machines', 'serial_number')
            ->where(fn ($query) => $query
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at'));

        if ($machine) {
            $uniqueSerial->ignore($machine->id);
        }

        $validated = $request->validate([
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id),
            ],
            'work_center_id' => [
                'required',
                'integer',
                Rule::exists('work_centers', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('department_id', $request->input('department_id'))
                    ->whereNull('deleted_at'),
            ],
            'code' => ['required', 'string', 'max:20', $uniqueCode],
            'name' => ['required', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100', $uniqueSerial],
            'asset_tag' => ['nullable', 'string', 'max:100'],
            'installation_date' => ['nullable', 'date'],
            'purchase_date' => ['nullable', 'date'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(Machine::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // department_id is validated for cascade UI rules, but persisted from the work center.
        unset($validated['department_id']);

        return $validated;
    }

    private function machineBelongsToActivePlant($user, Machine $machine): bool
    {
        return (int) $machine->organization_id === (int) $user->organization_id
            && (int) $machine->plant_id === (int) $user->active_plant_id;
    }
}
