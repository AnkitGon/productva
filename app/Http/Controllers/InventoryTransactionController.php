<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryTransactionController extends Controller
{
    /**
     * Global inventory transaction ledger for the active plant.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id || ! $user->active_plant_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $query = InventoryTransaction::query()
            ->forActivePlant($user)
            ->with([
                'product:id,sku,name,uom_id',
                'product.uom:id,code,symbol',
                'warehouse:id,code,name',
                'location:id,code,name',
                'fromWarehouse:id,code,name',
                'fromLocation:id,code,name',
                'toWarehouse:id,code,name',
                'toLocation:id,code,name',
                'creator:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_no', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('lot_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('inventory_id')) {
            $query->where('inventory_id', $request->inventory_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('warehouse_location_id')) {
            $query->where('warehouse_location_id', $request->warehouse_location_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('lot_number')) {
            $query->where('lot_number', $request->lot_number);
        }

        if ($request->filled('serial_number')) {
            $query->where('serial_number', $request->serial_number);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transacted_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transacted_at', '<=', $request->date_to);
        }

        $query->orderByDesc('transacted_at')->orderByDesc('id');

        $warehouses = Warehouse::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $locations = WarehouseLocation::query()
            ->forActivePlant($user)
            ->where('status', 'Active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'warehouse_id']);

        $products = Product::query()
            ->forOrganization($user)
            ->where('track_inventory', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);

        return Inertia::render('inventory-transactions/index', [
            'transactions' => $query->paginate($perPage)->withQueryString(),
            'warehouses' => $warehouses,
            'locations' => $locations,
            'products' => $products,
            'transactionTypes' => InventoryTransaction::TYPES,
            'plant' => $user->activePlant?->only(['id', 'name', 'code']),
            'filters' => $request->only([
                'search',
                'inventory_id',
                'product_id',
                'warehouse_id',
                'warehouse_location_id',
                'transaction_type',
                'lot_number',
                'serial_number',
                'date_from',
                'date_to',
                'per_page',
            ]),
        ]);
    }
}
