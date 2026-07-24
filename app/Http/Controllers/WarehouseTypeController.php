<?php

namespace App\Http\Controllers;

use App\Models\WarehouseType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WarehouseTypeController extends Controller
{
    /**
     * Display organization-wide warehouse types.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        WarehouseType::ensureDefaultsFor((int) $user->organization_id);

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = WarehouseType::query()
            ->forOrganization($user)
            ->withCount('warehouses');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $allowedSorts = ['code', 'name', 'status', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'code';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        return Inertia::render('warehouse-types/index', [
            'warehouseTypes' => $query->paginate($perPage)->withQueryString(),
            'statuses' => WarehouseType::STATUSES,
            'filters' => $request->only(['search', 'status', 'sort_by', 'sort_dir', 'per_page']),
        ]);
    }

    /**
     * Store a newly created warehouse type.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $validated = $this->validateType($request, $user);

        WarehouseType::create([
            ...$validated,
            'organization_id' => $user->organization_id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Warehouse type created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified warehouse type.
     */
    public function update(Request $request, WarehouseType $warehouseType)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $warehouseType)) {
            abort(403);
        }

        $validated = $this->validateType($request, $user, $warehouseType);

        if (empty($validated['code'])) {
            unset($validated['code']);
        }

        $warehouseType->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Warehouse type updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Archive the specified warehouse type.
     */
    public function destroy(Request $request, WarehouseType $warehouseType)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $warehouseType)) {
            abort(403);
        }

        if ($warehouseType->warehouses()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot archive this warehouse type while warehouses are assigned to it.',
            ]);

            return redirect()->back();
        }

        $warehouseType->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Warehouse type archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateType(Request $request, $user, ?WarehouseType $warehouseType = null): array
    {
        $request->merge([
            'code' => $request->filled('code') ? strtoupper(trim((string) $request->input('code'))) : null,
            'description' => $request->filled('description') ? $request->input('description') : null,
        ]);

        $uniqueCode = Rule::unique('warehouse_types', 'code')
            ->where(fn ($query) => $query
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at'));

        if ($warehouseType) {
            $uniqueCode->ignore($warehouseType->id);
        }

        return $request->validate([
            'code' => ['nullable', 'string', 'max:20', $uniqueCode],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(WarehouseType::STATUSES)],
        ]);
    }

    private function belongsToOrganization($user, WarehouseType $warehouseType): bool
    {
        return (int) $warehouseType->organization_id === (int) $user->organization_id;
    }
}
