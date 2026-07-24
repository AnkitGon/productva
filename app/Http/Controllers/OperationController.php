<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OperationController extends Controller
{
    /**
     * List operation masters for the organization.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = Operation::query()->forOrganization($user);

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

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $allowedSorts = ['code', 'name', 'type', 'status', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'code';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        return Inertia::render('operations/index', [
            'operations' => $query->paginate($perPage)->withQueryString(),
            'statuses' => Operation::STATUSES,
            'types' => Operation::TYPES,
            'filters' => $request->only(['search', 'status', 'type', 'sort_by', 'sort_dir', 'per_page']),
        ]);
    }

    /**
     * Store a new operation master.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $validated = $this->validateOperation($request, $user);

        Operation::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Operation created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update an operation master.
     */
    public function update(Request $request, Operation $operation)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $operation)) {
            abort(403);
        }

        $validated = $this->validateOperation($request, $user, $operation);

        $updateData = [
            ...$validated,
            'updated_by' => $user->id,
        ];
        if (empty($updateData['code'])) {
            unset($updateData['code']);
        }

        $operation->update($updateData);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Operation updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Soft-delete an operation master.
     */
    public function destroy(Request $request, Operation $operation)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $operation)) {
            abort(403);
        }

        if ($operation->routingOperations()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot delete an operation that is used on routings.',
            ]);

            return redirect()->back();
        }

        $operation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Operation deleted successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOperation(Request $request, $user, ?Operation $operation = null): array
    {
        return $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('operations', 'code')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at')
                    ->ignore($operation?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Operation::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(Operation::STATUSES)],
        ]);
    }

    private function belongsToOrganization($user, Operation $operation): bool
    {
        return (int) $operation->organization_id === (int) $user->organization_id;
    }
}
