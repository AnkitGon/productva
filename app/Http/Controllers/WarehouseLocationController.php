<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WarehouseLocationController extends Controller
{
    /**
     * Display warehouse locations for the active plant.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        $query = WarehouseLocation::query()
            ->forActivePlant($user)
            ->with([
                'warehouse:id,code,name',
                'parent:id,code,name,type',
                'children:id,parent_id',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Fetch all matching locations to build hierarchy tree sorting in memory
        $rawLocations = $query->get();
        $sortedLocations = $this->sortHierarchically($rawLocations, $request->get('sort_by'), $request->get('sort_dir'));

        // Manually paginate the sorted array
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = array_slice($sortedLocations, ($currentPage - 1) * $perPage, $perPage);
        $paginatedLocations = new LengthAwarePaginator(
            $currentItems,
            count($sortedLocations),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
        $paginatedLocations->appends($request->all());

        $warehouses = Warehouse::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        // All active locations in plant for parent dropdown choices
        $parentOptions = WarehouseLocation::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->with('parent:id,name')
            ->orderBy('warehouse_id')
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'warehouse_id', 'parent_id', 'type', 'code', 'name']);

        return Inertia::render('warehouse-locations/index', [
            'locations' => $paginatedLocations,
            'warehouses' => $warehouses,
            'parentOptions' => $parentOptions,
            'types' => WarehouseLocation::TYPES,
            'statuses' => WarehouseLocation::STATUSES,
            'plant' => $user->activePlant?->only(['id', 'name', 'code']),
            'filters' => $request->only([
                'search',
                'warehouse_id',
                'type',
                'status',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        ]);
    }

    /**
     * Sort warehouse locations hierarchically.
     */
    private function sortHierarchically($locations, ?string $sortBy = null, ?string $sortDir = null): array
    {
        $locationMap = [];
        foreach ($locations as $loc) {
            $locationMap[$loc->id] = $loc;
        }

        // Find roots (parent_id is null or parent_id not in current filtered list)
        $roots = [];
        foreach ($locations as $loc) {
            if (is_null($loc->parent_id) || ! isset($locationMap[$loc->parent_id])) {
                $roots[] = $loc;
            }
        }

        // Sort roots
        $sortBy = $sortBy ?: 'code';
        $sortDir = $sortDir === 'desc' ? 'desc' : 'asc';
        usort($roots, function ($a, $b) use ($sortBy, $sortDir) {
            $valA = strtolower((string) ($a->{$sortBy} ?? ''));
            $valB = strtolower((string) ($b->{$sortBy} ?? ''));

            return $sortDir === 'desc' ? strcmp($valB, $valA) : strcmp($valA, $valB);
        });

        $result = [];
        foreach ($roots as $root) {
            $this->traverseTree($root, $locationMap, 0, $result, $sortBy, $sortDir);
        }

        return $result;
    }

    /**
     * Recursively traverse the location map to append children immediately following parents.
     */
    private function traverseTree($node, $locationMap, $depth, &$result, string $sortBy, string $sortDir): void
    {
        $node->depth = $depth;
        $result[] = $node;

        $children = [];
        foreach ($locationMap as $loc) {
            if ((int) $loc->parent_id === (int) $node->id) {
                $children[] = $loc;
            }
        }

        usort($children, function ($a, $b) use ($sortBy, $sortDir) {
            $valA = strtolower((string) ($a->{$sortBy} ?? ''));
            $valB = strtolower((string) ($b->{$sortBy} ?? ''));

            return $sortDir === 'desc' ? strcmp($valB, $valA) : strcmp($valA, $valB);
        });

        foreach ($children as $child) {
            $this->traverseTree($child, $locationMap, $depth + 1, $result, $sortBy, $sortDir);
        }
    }

    /**
     * Store a newly created warehouse location.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $this->validateLocation($request, $user);

        WarehouseLocation::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Location '{$validated['code']}' created successfully.",
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified warehouse location.
     */
    public function update(Request $request, WarehouseLocation $location)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToPlant($user, $location)) {
            abort(403);
        }

        $validated = $this->validateLocation($request, $user, $location);

        // Circular parent check
        if (! empty($validated['parent_id'])) {
            if ((int) $validated['parent_id'] === (int) $location->id) {
                return redirect()->back()->withErrors([
                    'parent_id' => 'A location cannot be its own parent.',
                ]);
            }

            if ($location->isDescendantOf((int) $validated['parent_id'])) {
                return redirect()->back()->withErrors([
                    'parent_id' => 'Cannot set parent to a location that is nested under this location.',
                ]);
            }
        }

        $updateData = [
            ...$validated,
            'updated_by' => $user->id,
        ];
        if (empty($updateData['code'])) {
            unset($updateData['code']);
        }

        $location->update($updateData);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Location '{$location->code}' updated successfully.",
        ]);

        return redirect()->back();
    }

    /**
     * Delete the specified warehouse location.
     */
    public function destroy(Request $request, WarehouseLocation $location)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToPlant($user, $location)) {
            abort(403);
        }

        if ($location->children()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot delete this location because it has child locations assigned to it. Please reassign or delete child locations first.',
            ]);

            return redirect()->back();
        }

        $location->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Location deleted successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLocation(Request $request, $user, ?WarehouseLocation $location = null): array
    {
        $request->merge([
            'code' => $request->filled('code') ? strtoupper(trim((string) $request->input('code'))) : null,
            'name' => trim((string) $request->input('name', '')),
            'barcode' => $request->filled('barcode') ? trim((string) $request->input('barcode')) : null,
            'parent_id' => $request->filled('parent_id') && $request->input('parent_id') !== 'none' ? $request->input('parent_id') : null,
        ]);

        $uniqueCode = Rule::unique('warehouse_locations', 'code')
            ->where('warehouse_id', $request->input('warehouse_id'))
            ->whereNull('deleted_at');

        if ($location) {
            $uniqueCode->ignore($location->id);
        }

        return $request->validate([
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouse_locations', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::in(WarehouseLocation::TYPES)],
            'code' => ['nullable', 'string', 'max:50', $uniqueCode],
            'name' => ['required', 'string', 'max:150'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(WarehouseLocation::STATUSES)],
        ]);
    }

    private function belongsToPlant($user, WarehouseLocation $location): bool
    {
        return (int) $location->organization_id === (int) $user->organization_id
            && (int) $location->warehouse->plant_id === (int) $user->active_plant_id;
    }
}
