<?php

namespace App\Http\Controllers;

use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitOfMeasureController extends Controller
{
    /**
     * Display a listing of units of measure for the organization.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = UnitOfMeasure::query()
            ->forOrganization($user)
            ->withCount('products');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $allowedSorts = ['code', 'name', 'symbol', 'type', 'decimal_places', 'status', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'code';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        return Inertia::render('units-of-measure/index', [
            'unitsOfMeasure' => $query->paginate($perPage)->withQueryString(),
            'types' => UnitOfMeasure::TYPES,
            'statuses' => UnitOfMeasure::STATUSES,
            'filters' => $request->only([
                'search',
                'status',
                'type',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        ]);
    }

    /**
     * Store a newly created unit of measure.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $validated = $this->validateUnitOfMeasure($request, $user);

        UnitOfMeasure::create([
            ...$validated,
            'organization_id' => $user->organization_id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Unit of measure created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified unit of measure.
     */
    public function update(Request $request, UnitOfMeasure $unitOfMeasure)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $unitOfMeasure)) {
            abort(403);
        }

        $validated = $this->validateUnitOfMeasure($request, $user, $unitOfMeasure);

        if (empty($validated['code'])) {
            unset($validated['code']);
        }

        $unitOfMeasure->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Unit of measure updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Archive (soft delete) the specified unit of measure.
     */
    public function destroy(Request $request, UnitOfMeasure $unitOfMeasure)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $unitOfMeasure)) {
            abort(403);
        }

        if ($unitOfMeasure->isReferencedByProduct()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot archive this unit of measure while it is referenced by a product.',
            ]);

            return redirect()->back();
        }

        $unitOfMeasure->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Unit of measure archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUnitOfMeasure(Request $request, $user, ?UnitOfMeasure $unitOfMeasure = null): array
    {
        $request->merge([
            'code' => $request->filled('code') ? strtoupper(trim((string) $request->input('code'))) : null,
            'name' => trim((string) $request->input('name', '')),
            'symbol' => trim((string) $request->input('symbol', '')),
            'description' => $request->filled('description') ? $request->input('description') : null,
        ]);

        $uniqueCode = Rule::unique('units_of_measure', 'code')
            ->where(fn ($query) => $query
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at'));

        $uniqueName = Rule::unique('units_of_measure', 'name')
            ->where(fn ($query) => $query
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at'));

        if ($unitOfMeasure) {
            $uniqueCode->ignore($unitOfMeasure->id);
            $uniqueName->ignore($unitOfMeasure->id);
        }

        return $request->validate([
            'code' => ['nullable', 'string', 'max:20', $uniqueCode],
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'symbol' => ['required', 'string', 'max:20'],
            'type' => ['required', Rule::in(UnitOfMeasure::TYPES)],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'status' => ['required', Rule::in(UnitOfMeasure::STATUSES)],
            'description' => ['nullable', 'string', 'max:5000'],
        ], [
            'name.unique' => 'A unit of measure with this name already exists.',
        ]);
    }

    private function belongsToOrganization($user, UnitOfMeasure $unitOfMeasure): bool
    {
        return (int) $unitOfMeasure->organization_id === (int) $user->organization_id;
    }
}
