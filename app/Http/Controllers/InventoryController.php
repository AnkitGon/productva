<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseType;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Inventory list with dashboard cards for the active plant.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $baseQuery = Inventory::query()
            ->forActivePlant($user)
            ->with([
                'product:id,sku,name,barcode,category_id,reorder_level,minimum_stock,purchase_price,lot_tracking,serial_tracking,uom_id',
                'product.category:id,code,name',
                'product.uom:id,code,name,symbol',
                'warehouse:id,code,name,warehouse_type_id',
                'warehouse.warehouseType:id,code,name',
                'location:id,code,name,warehouse_id',
            ]);

        $this->applyFilters($baseQuery, $request);

        $summaryQuery = Inventory::query()->forActivePlant($user);
        $this->applyFilters($summaryQuery, $request);

        $summaryRows = (clone $summaryQuery)
            ->with(['product:id,reorder_level,minimum_stock,purchase_price'])
            ->get(['id', 'product_id', 'quantity_on_hand', 'quantity_reserved']);

        $dashboard = [
            'total_products' => $summaryRows->pluck('product_id')->unique()->count(),
            'total_stock_value' => round($summaryRows->sum(function (Inventory $row) {
                $price = (float) ($row->product?->purchase_price ?? 0);

                return max((float) $row->quantity_on_hand, 0) * $price;
            }), 2),
            'out_of_stock' => $summaryRows->filter(fn (Inventory $row) => (float) $row->quantity_on_hand == 0.0)->count(),
            'low_stock' => $summaryRows->filter(function (Inventory $row) {
                $onHand = (float) $row->quantity_on_hand;
                if ($onHand <= 0) {
                    return false;
                }
                $level = $row->product?->minimum_stock ?? $row->product?->reorder_level;

                return $level !== null && $onHand < (float) $level;
            })->count(),
            'negative_stock' => $summaryRows->filter(fn (Inventory $row) => (float) $row->quantity_on_hand < 0)->count(),
        ];

        $allowedSorts = ['quantity_on_hand', 'quantity_reserved', 'last_movement_at', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'last_movement_at';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';
        $baseQuery->orderBy($sortBy, $sortDir);

        $warehouses = Warehouse::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'warehouse_type_id']);

        $locations = WarehouseLocation::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'warehouse_id']);

        $categories = ProductCategory::query()
            ->forOrganization($user)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $warehouseTypes = WarehouseType::query()
            ->forOrganization($user)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $products = Product::query()
            ->forOrganization($user)
            ->where('track_inventory', true)
            ->where('status', 'Active')
            ->with(['uom:id,code,name,symbol'])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'lot_tracking', 'serial_tracking', 'uom_id']);

        return Inertia::render('inventory/index', [
            'inventories' => $baseQuery->paginate($perPage)->withQueryString(),
            'dashboard' => $dashboard,
            'warehouses' => $warehouses,
            'locations' => $locations,
            'categories' => $categories,
            'warehouseTypes' => $warehouseTypes,
            'products' => $products,
            'stockStatuses' => Inventory::STOCK_STATUSES,
            'plant' => $user->activePlant?->only(['id', 'name', 'code']),
            'filters' => $request->only([
                'search',
                'warehouse_id',
                'warehouse_location_id',
                'product_id',
                'category_id',
                'warehouse_type_id',
                'lot_number',
                'serial_number',
                'stock_status',
                'low_stock',
                'out_of_stock',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        ]);
    }

    /**
     * Redirect balance history to the filtered inventory transactions ledger.
     */
    public function history(Request $request, Inventory $inventory)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToActivePlant($user, $inventory)) {
            abort(403);
        }

        return redirect()->route('inventory-transactions.index', array_filter([
            'inventory_id' => $inventory->id,
            'product_id' => $inventory->product_id,
            'warehouse_id' => $inventory->warehouse_id,
            'warehouse_location_id' => $inventory->warehouse_location_id,
            'lot_number' => $inventory->lot_number !== '' ? $inventory->lot_number : null,
            'serial_number' => $inventory->serial_number !== '' ? $inventory->serial_number : null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Lookup current on-hand for a stock combination (used by the adjust form).
     */
    public function balance(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'warehouse_location_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'lot_number' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
        ]);

        $product = Product::query()
            ->where('organization_id', $user->organization_id)
            ->with(['uom:id,code,name,symbol'])
            ->findOrFail($validated['product_id']);

        $inventory = $this->inventoryService->findBalance(
            $user,
            (int) $validated['warehouse_id'],
            (int) $validated['warehouse_location_id'],
            (int) $validated['product_id'],
            $validated['lot_number'] ?? null,
            $validated['serial_number'] ?? null,
        );

        return response()->json([
            'quantity_on_hand' => $inventory ? (float) $inventory->quantity_on_hand : 0,
            'quantity_reserved' => $inventory ? (float) $inventory->quantity_reserved : 0,
            'uom' => $product->uom?->only(['id', 'code', 'name', 'symbol']),
        ]);
    }

    /**
     * Stock adjustment — set absolute on-hand quantity.
     */
    public function adjust(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'warehouse_location_id' => [
                'required',
                'integer',
                Rule::exists('warehouse_locations', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('warehouse_id', $request->input('warehouse_id'))
                    ->whereNull('deleted_at'),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'new_quantity' => ['required', 'numeric'],
            'lot_number' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ]);

        $this->inventoryService->adjust($user, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Stock adjustment recorded successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Transfer stock between locations.
     */
    public function transfer(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'from_warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'from_location_id' => [
                'required',
                'integer',
                Rule::exists('warehouse_locations', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('warehouse_id', $request->input('from_warehouse_id'))
                    ->whereNull('deleted_at'),
            ],
            'to_warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('plant_id', $user->active_plant_id)
                    ->whereNull('deleted_at'),
            ],
            'to_location_id' => [
                'required',
                'integer',
                Rule::exists('warehouse_locations', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('warehouse_id', $request->input('to_warehouse_id'))
                    ->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'lot_number' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ]);

        $this->inventoryService->transfer($user, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Stock transfer recorded successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Export inventory balances as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $query = Inventory::query()
            ->forActivePlant($user)
            ->with([
                'product:id,sku,name,barcode',
                'warehouse:id,code,name',
                'location:id,code,name',
            ]);

        $this->applyFilters($query, $request);
        $rows = $query->orderBy('id')->get();

        $filename = 'inventory-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SKU', 'Product', 'Warehouse', 'Location', 'Lot', 'Serial',
                'On Hand', 'Reserved', 'Available', 'Status', 'Last Movement',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->product?->sku,
                    $row->product?->name,
                    $row->warehouse?->code,
                    $row->location?->code,
                    $row->lot_number ?: null,
                    $row->serial_number ?: null,
                    $row->quantity_on_hand,
                    $row->quantity_reserved,
                    $row->quantity_available,
                    $row->stock_status,
                    $row->last_movement_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('lot_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('warehouse_location_id')) {
            $query->where('warehouse_location_id', $request->warehouse_location_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('category_id')) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $request->category_id));
        }

        if ($request->filled('warehouse_type_id')) {
            $query->whereHas('warehouse', fn ($q) => $q->where('warehouse_type_id', $request->warehouse_type_id));
        }

        if ($request->filled('lot_number')) {
            $query->where('lot_number', $request->lot_number);
        }

        if ($request->filled('serial_number')) {
            $query->where('serial_number', $request->serial_number);
        }

        if ($request->boolean('low_stock')) {
            $query->withStockStatusFilters('Low Stock');
        } elseif ($request->boolean('out_of_stock')) {
            $query->withStockStatusFilters('Out Of Stock');
        } elseif ($request->filled('stock_status')) {
            $query->withStockStatusFilters($request->stock_status);
        }
    }

    private function belongsToActivePlant($user, Inventory $inventory): bool
    {
        return (int) $inventory->organization_id === (int) $user->organization_id
            && (int) $inventory->plant_id === (int) $user->active_plant_id;
    }
}
