<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\Operation;
use App\Models\Product;
use App\Models\RoutingHeader;
use App\Models\WorkCenter;
use App\Services\RoutingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoutingController extends Controller
{
    public function __construct(private RoutingService $routingService) {}

    /**
     * List routings for the active plant.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = RoutingHeader::query()
            ->forActivePlant($user)
            ->with(['product:id,sku,name,type'])
            ->withCount('operations');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('version', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('is_default') && $request->input('is_default') !== '' && $request->input('is_default') !== 'all') {
            $query->where('is_default', $request->boolean('is_default'));
        }

        $allowedSorts = ['version', 'status', 'effective_from', 'created_at', 'is_default'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'created_at';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        $products = Product::query()
            ->forOrganization($user)
            ->whereIn('type', ['Finished Good', 'Semi Finished'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type']);

        return Inertia::render('routings/index', [
            'routings' => $query->paginate($perPage)->withQueryString(),
            'products' => $products,
            'statuses' => RoutingHeader::STATUSES,
            'plant' => $user->activePlant?->only(['id', 'name', 'code']),
            'filters' => $request->only([
                'search',
                'status',
                'product_id',
                'is_default',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        ]);
    }

    /**
     * Show create routing form.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        return Inertia::render('routings/create', $this->formOptions($user, $request->integer('product_id') ?: null));
    }

    /**
     * Store a routing.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $this->validateRouting($request, $user);
        $routing = $this->routingService->create($user, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Routing created successfully.',
        ]);

        return redirect()->route('routings.show', $routing);
    }

    /**
     * Show a routing.
     */
    public function show(Request $request, RoutingHeader $routing)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $routing)) {
            abort(403);
        }

        $routing->load([
            'product:id,sku,name,type',
            'operations.workCenter:id,code,name',
            'operations.machine:id,code,name,work_center_id',
            'operations.operation:id,code,name',
        ]);

        $times = $routing->estimatedTimes();

        return Inertia::render('routings/show', [
            'routing' => $routing,
            'summary' => [
                'version' => $routing->version,
                'status' => $routing->status,
                'is_default' => $routing->is_default,
                'operations_count' => $routing->operations->count(),
                'estimated_setup_time' => $times['setup'],
                'estimated_run_time' => $times['run'],
                'estimated_cycle_time' => $times['total'],
            ],
        ]);
    }

    /**
     * Show edit form.
     */
    public function edit(Request $request, RoutingHeader $routing)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $routing)) {
            abort(403);
        }

        if (! $routing->is_editable) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Only draft routings can be edited. Copy the routing to make changes.',
            ]);

            return redirect()->route('routings.show', $routing);
        }

        $routing->load([
            'operations.workCenter:id,code,name',
            'operations.machine:id,code,name,work_center_id',
            'operations.operation:id,code,name',
        ]);

        return Inertia::render('routings/edit', [
            'routing' => $routing,
            ...$this->formOptions($user),
        ]);
    }

    /**
     * Update a draft routing.
     */
    public function update(Request $request, RoutingHeader $routing)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $routing)) {
            abort(403);
        }

        $validated = $this->validateRouting($request, $user, $routing);
        $this->routingService->update($user, $routing, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Routing updated successfully.',
        ]);

        return redirect()->route('routings.show', $routing);
    }

    /**
     * Soft-delete a routing.
     */
    public function destroy(Request $request, RoutingHeader $routing)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $routing)) {
            abort(403);
        }

        $routing->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Routing deleted successfully.',
        ]);

        return redirect()->route('routings.index');
    }

    /**
     * Copy routing to next draft version.
     */
    public function copy(Request $request, RoutingHeader $routing)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $routing)) {
            abort(403);
        }

        $copy = $this->routingService->copy($user, $routing);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Routing copied as version {$copy->version}.",
        ]);

        return redirect()->route('routings.edit', $copy);
    }

    /**
     * Release a draft routing.
     */
    public function release(Request $request, RoutingHeader $routing)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $routing)) {
            abort(403);
        }

        $this->routingService->release($user, $routing);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Routing released successfully.',
        ]);

        return redirect()->route('routings.show', $routing);
    }

    /**
     * Mark routing obsolete.
     */
    public function obsolete(Request $request, RoutingHeader $routing)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $routing)) {
            abort(403);
        }

        $this->routingService->obsolete($user, $routing);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Routing marked obsolete.',
        ]);

        return redirect()->route('routings.show', $routing);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions($user, ?int $preselectedProductId = null): array
    {
        $products = Product::query()
            ->forOrganization($user)
            ->whereIn('type', ['Finished Good', 'Semi Finished'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type']);

        $operations = Operation::query()
            ->forOrganization($user)
            ->where('status', 'Active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $workCenters = WorkCenter::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $machines = Machine::query()
            ->forActivePlant($user)
            ->whereIn('status', Machine::ASSIGNABLE_STATUSES)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'work_center_id', 'status']);

        return [
            'products' => $products,
            'operations' => $operations,
            'workCenters' => $workCenters,
            'machines' => $machines,
            'statuses' => RoutingHeader::STATUSES,
            'preselectedProductId' => $preselectedProductId,
            'plant' => $user->activePlant?->only(['id', 'name', 'code']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRouting(Request $request, $user, ?RoutingHeader $routing = null): array
    {
        return $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'version' => [
                'required',
                'string',
                'max:50',
                Rule::unique('routing_headers', 'version')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->where('product_id', $request->input('product_id'))
                    ->whereNull('deleted_at')
                    ->ignore($routing?->id),
            ],
            'is_default' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', 'string', Rule::in(['Draft', 'Released'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'operations' => ['required', 'array', 'min:1'],
            'operations.*.sequence' => ['required', 'integer', 'min:1'],
            'operations.*.operation_id' => [
                'required',
                'integer',
                Rule::exists('operations', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'operations.*.work_center_id' => [
                'required',
                'integer',
                Rule::exists('work_centers', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'operations.*.machine_id' => [
                'nullable',
                'integer',
                Rule::exists('machines', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'operations.*.setup_time_minutes' => ['nullable', 'numeric', 'min:0'],
            'operations.*.run_time_per_unit' => ['required', 'numeric', 'gt:0'],
            'operations.*.labor_time' => ['nullable', 'numeric', 'min:0'],
            'operations.*.queue_time' => ['nullable', 'numeric', 'min:0'],
            'operations.*.move_time' => ['nullable', 'numeric', 'min:0'],
            'operations.*.wait_time' => ['nullable', 'numeric', 'min:0'],
            'operations.*.overlap_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'operations.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function belongsToActivePlant($user, RoutingHeader $routing): bool
    {
        return (int) $routing->organization_id === (int) $user->organization_id
            && (int) $routing->plant_id === (int) $user->active_plant_id;
    }
}
