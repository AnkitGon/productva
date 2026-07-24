<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    /**
     * Display warehouses for the active plant.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        WarehouseType::ensureDefaultsFor((int) $user->organization_id);

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = Warehouse::query()
            ->forActivePlant($user)
            ->with([
                'warehouseType:id,code,name',
                'manager:id,first_name,last_name,display_name,employee_code,user_id',
                'manager.user:id,name,email',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouses.name', 'like', "%{$search}%")
                    ->orWhere('warehouses.email', 'like', "%{$search}%")
                    ->orWhereHas('manager', function ($managerQuery) use ($search) {
                        $managerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('display_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('warehouse_type_id')) {
            $query->where('warehouse_type_id', $request->warehouse_type_id);
        }

        if ($request->filled('manager_employee_id')) {
            $query->where('manager_employee_id', $request->manager_employee_id);
        }

        $allowedSorts = ['code', 'name', 'status', 'is_default', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'name';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        $warehouseTypes = WarehouseType::query()
            ->forOrganization($user)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'status']);

        $managerIds = Warehouse::query()
            ->forActivePlant($user)
            ->whereNotNull('manager_employee_id')
            ->distinct()
            ->pluck('manager_employee_id');

        $managers = Employee::query()
            ->whereIn('id', $managerIds)
            ->with('user:id,name,email')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'display_name', 'employee_code', 'user_id']);

        return Inertia::render('warehouses/index', [
            'warehouses' => $query->paginate($perPage)->withQueryString(),
            'warehouseTypes' => $warehouseTypes,
            'managers' => $managers,
            'statuses' => Warehouse::STATUSES,
            'plant' => $user->activePlant?->only(['id', 'name', 'code']),
            'filters' => $request->only([
                'search',
                'status',
                'warehouse_type_id',
                'manager_employee_id',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        ]);
    }

    /**
     * Store a newly created warehouse for the active plant.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $this->validateWarehouse($request, $user);

        DB::transaction(function () use ($validated, $user) {
            if (! empty($validated['is_default'])) {
                Warehouse::query()
                    ->where('plant_id', $user->active_plant_id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            Warehouse::create([
                ...$validated,
                'organization_id' => $user->organization_id,
                'plant_id' => $user->active_plant_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Warehouse created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified warehouse.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $warehouse)) {
            abort(403);
        }

        $validated = $this->validateWarehouse($request, $user, $warehouse);

        DB::transaction(function () use ($validated, $user, $warehouse) {
            if (! empty($validated['is_default'])) {
                Warehouse::query()
                    ->where('plant_id', $warehouse->plant_id)
                    ->where('is_default', true)
                    ->where('id', '!=', $warehouse->id)
                    ->update(['is_default' => false]);
            }

            $updateData = [
                ...$validated,
                'updated_by' => $user->id,
            ];
            if (empty($updateData['code'])) {
                unset($updateData['code']);
            }

            $warehouse->update($updateData);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Warehouse updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Archive or deactivate the specified warehouse.
     */
    public function destroy(Request $request, Warehouse $warehouse)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $warehouse)) {
            abort(403);
        }

        if ($warehouse->hasBlockingDependencies()) {
            $warehouse->update([
                'status' => 'Inactive',
                'updated_by' => $user->id,
            ]);

            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Warehouse has inventory or history, so it was marked Inactive instead of archived.',
            ]);

            return redirect()->back();
        }

        $warehouse->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Warehouse archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWarehouse(Request $request, $user, ?Warehouse $warehouse = null): array
    {
        $request->merge([
            'code' => $request->filled('code') ? strtoupper(trim((string) $request->input('code'))) : null,
            'manager_employee_id' => $request->filled('manager_employee_id') ? $request->input('manager_employee_id') : null,
            'phone' => $request->filled('phone') ? $request->input('phone') : null,
            'email' => $request->filled('email') ? $request->input('email') : null,
            'address_line_1' => $request->filled('address_line_1') ? $request->input('address_line_1') : null,
            'address_line_2' => $request->filled('address_line_2') ? $request->input('address_line_2') : null,
            'city' => $request->filled('city') ? $request->input('city') : null,
            'state' => $request->filled('state') ? $request->input('state') : null,
            'postal_code' => $request->filled('postal_code') ? $request->input('postal_code') : null,
            'country' => $request->filled('country') ? $request->input('country') : null,
            'notes' => $request->filled('notes') ? $request->input('notes') : null,
            'allow_negative_stock' => $request->boolean('allow_negative_stock'),
            'is_default' => $request->boolean('is_default'),
        ]);

        $uniqueCode = Rule::unique('warehouses', 'code')
            ->where(fn ($query) => $query
                ->where('plant_id', $user->active_plant_id)
                ->whereNull('deleted_at'));

        if ($warehouse) {
            $uniqueCode->ignore($warehouse->id);
        }

        $activeType = Rule::exists('warehouse_types', 'id')
            ->where('organization_id', $user->organization_id)
            ->where('status', 'Active')
            ->whereNull('deleted_at');

        if ($warehouse && (int) $request->input('warehouse_type_id') === (int) $warehouse->warehouse_type_id) {
            $activeType = Rule::exists('warehouse_types', 'id')
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at');
        }

        return $request->validate([
            'warehouse_type_id' => ['required', 'integer', $activeType],
            'code' => ['nullable', 'string', 'max:50', $uniqueCode],
            'name' => ['required', 'string', 'max:150'],
            'manager_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(Warehouse::STATUSES)],
        ]);
    }

    private function belongsToActivePlant($user, Warehouse $warehouse): bool
    {
        return (int) $warehouse->organization_id === (int) $user->organization_id
            && (int) $warehouse->plant_id === (int) $user->active_plant_id;
    }
}
