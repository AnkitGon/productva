<?php

namespace App\Http\Controllers;

use App\Models\BomHeader;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\BomService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BomController extends Controller
{
    public function __construct(private BomService $bomService) {}

    /**
     * List BOMs for the organization.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = BomHeader::query()
            ->forOrganization($user)
            ->with([
                'product:id,sku,name,type,uom_id',
                'product.uom:id,code,symbol',
            ])
            ->withCount('items');

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

        return Inertia::render('boms/index', [
            'boms' => $query->paginate($perPage)->withQueryString(),
            'products' => $products,
            'statuses' => BomHeader::STATUSES,
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
     * Show the create BOM form.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        return Inertia::render('boms/create', $this->formOptions($user, $request->integer('product_id') ?: null));
    }

    /**
     * Store a new BOM.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $validated = $this->validateBom($request, $user);
        $bom = $this->bomService->create($user, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'BOM created successfully.',
        ]);

        return redirect()->route('boms.show', $bom);
    }

    /**
     * Show a BOM.
     */
    public function show(Request $request, BomHeader $bom)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $bom)) {
            abort(403);
        }

        $bom->load([
            'product:id,sku,name,type,uom_id',
            'product.uom:id,code,name,symbol',
            'items.component:id,sku,name,type,uom_id',
            'items.component.uom:id,code,symbol',
            'items.uom:id,code,name,symbol',
            'creator:id,name',
            'updater:id,name',
        ]);

        return Inertia::render('boms/show', [
            'bom' => $bom,
            'summary' => [
                'version' => $bom->version,
                'status' => $bom->status,
                'is_default' => $bom->is_default,
                'components_count' => $bom->items->count(),
                'estimated_material_cost' => null,
            ],
        ]);
    }

    /**
     * Copy a BOM to a new Draft version.
     */
    public function copy(Request $request, BomHeader $bom)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $bom)) {
            abort(403);
        }

        $copy = $this->bomService->copy($user, $bom);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "BOM copied as version {$copy->version}.",
        ]);

        return redirect()->route('boms.edit', $copy);
    }

    /**
     * Show the edit BOM form.
     */
    public function edit(Request $request, BomHeader $bom)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $bom)) {
            abort(403);
        }

        $bom->load([
            'items.component:id,sku,name,type,uom_id',
            'items.uom:id,code,name,symbol',
        ]);

        return Inertia::render('boms/edit', [
            'bom' => $bom,
            ...$this->formOptions($user),
        ]);
    }

    /**
     * Update a BOM.
     */
    public function update(Request $request, BomHeader $bom)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $bom)) {
            abort(403);
        }

        $validated = $this->validateBom($request, $user, $bom);
        $this->bomService->update($user, $bom, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'BOM updated successfully.',
        ]);

        return redirect()->route('boms.show', $bom);
    }

    /**
     * Soft-delete a BOM.
     */
    public function destroy(Request $request, BomHeader $bom)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $bom)) {
            abort(403);
        }

        $bom->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'BOM deleted successfully.',
        ]);

        return redirect()->route('boms.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions($user, ?int $preselectedProductId = null): array
    {
        $parentProducts = Product::query()
            ->forOrganization($user)
            ->whereIn('type', ['Finished Good', 'Semi Finished'])
            ->where('status', 'Active')
            ->with(['uom:id,code,name,symbol'])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type', 'uom_id']);

        $componentProducts = Product::query()
            ->forOrganization($user)
            ->where('status', 'Active')
            ->where('type', '!=', 'Service')
            ->with(['uom:id,code,name,symbol'])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type', 'uom_id']);

        $uoms = UnitOfMeasure::query()
            ->forOrganization($user)
            ->where('status', 'Active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol']);

        return [
            'parentProducts' => $parentProducts,
            'componentProducts' => $componentProducts,
            'uoms' => $uoms,
            'statuses' => BomHeader::STATUSES,
            'preselectedProductId' => $preselectedProductId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBom(Request $request, $user, ?BomHeader $bom = null): array
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
                Rule::unique('bom_headers', 'version')
                    ->where('organization_id', $user->organization_id)
                    ->where('product_id', $request->input('product_id'))
                    ->whereNull('deleted_at')
                    ->ignore($bom?->id),
            ],
            'is_default' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', 'string', Rule::in(BomHeader::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_product_id' => [
                'required',
                'integer',
                'different:product_id',
                Rule::exists('products', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.uom_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'items.*.scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.sequence' => ['nullable', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function belongsToOrganization($user, BomHeader $bom): bool
    {
        return (int) $bom->organization_id === (int) $user->organization_id;
    }
}
