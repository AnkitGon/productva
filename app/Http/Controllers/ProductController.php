<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductAttachment;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of products for the organization.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = Product::query()
            ->forOrganization($user)
            ->with([
                'category:id,code,name',
                'uom:id,code,name,symbol',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('supplier_sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('uom_id')) {
            $query->where('uom_id', $request->uom_id);
        }

        if ($request->has('track_inventory') && $request->input('track_inventory') !== '' && $request->input('track_inventory') !== 'all') {
            $query->where('track_inventory', $request->boolean('track_inventory'));
        }

        if ($request->filled('default_warehouse_id')) {
            $query->where('default_warehouse_id', $request->default_warehouse_id);
        }

        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', $request->manufacturer);
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        if ($request->filled('preferred_supplier_id')) {
            $query->where('preferred_supplier_id', $request->preferred_supplier_id);
        }

        $allowedSorts = ['sku', 'name', 'type', 'status', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'name';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        $categories = ProductCategory::query()
            ->forOrganization($user)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        $warehouses = Warehouse::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        $manufacturers = Product::query()
            ->forOrganization($user)
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer');

        $brands = Product::query()
            ->forOrganization($user)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $uoms = UnitOfMeasure::query()
            ->forOrganization($user)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('products/index', [
            'products' => $query->paginate($perPage)->withQueryString(),
            'categories' => $categories,
            'warehouses' => $warehouses,
            'manufacturers' => $manufacturers,
            'brands' => $brands,
            'uoms' => $uoms,
            'types' => Product::TYPES,
            'statuses' => Product::STATUSES,
            'filters' => $request->only([
                'search',
                'status',
                'type',
                'category_id',
                'uom_id',
                'track_inventory',
                'default_warehouse_id',
                'manufacturer',
                'brand',
                'preferred_supplier_id',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        return Inertia::render('products/create', $this->formOptions($user));
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $validated = $this->validateProduct($request, $user);
        $paths = $this->storeMediaFiles($request);

        $product = Product::create([
            ...$validated,
            ...$paths,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->storeNewAttachments($request, $product, $user);

        $this->postOpeningStock($user, $product);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product created successfully.',
        ]);

        return redirect()->route('products.index');
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, Product $product)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $product)) {
            abort(403);
        }

        $product->load([
            'category:id,code,name,status',
            'uom:id,code,name,symbol,status',
            'purchaseUom:id,code,name,symbol,status',
            'creator:id,name',
            'updater:id,name',
            'attachments',
        ]);

        return Inertia::render('products/show', [
            'product' => $product,
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Request $request, Product $product)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $product)) {
            abort(403);
        }

        $product->load([
            'category:id,code,name,status',
            'uom:id,code,name,symbol,status',
            'purchaseUom:id,code,name,symbol,status',
            'attachments',
        ]);

        return Inertia::render('products/edit', [
            'product' => $product,
            ...$this->formOptions($user, $product),
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $product)) {
            abort(403);
        }

        $validated = $this->validateProduct($request, $user, $product);
        $paths = $this->storeMediaFiles($request, $product);

        $product->update([
            ...$validated,
            ...$paths,
            'updated_by' => $user->id,
        ]);

        // Delete individually removed attachments
        $deleteIds = array_filter(array_map('intval', (array) $request->input('delete_attachment_ids', [])));
        if ($deleteIds !== []) {
            $attachments = $product->attachments()->whereIn('id', $deleteIds)->get();
            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
                $attachment->delete();
            }
        }

        $this->storeNewAttachments($request, $product, $user);

        $this->postOpeningStock($user, $product);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product updated successfully.',
        ]);

        return redirect()->route('products.index');
    }

    /**
     * Delete a single product attachment.
     */
    public function destroyAttachment(Request $request, Product $product, ProductAttachment $attachment)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $product)) {
            abort(403);
        }

        if ((int) $attachment->product_id !== (int) $product->id) {
            abort(404);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Attachment removed.',
        ]);

        return redirect()->back();
    }

    /**
     * Archive or deactivate the specified product.
     */
    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $product)) {
            abort(403);
        }

        if ($product->hasBlockingDependencies()) {
            $product->update([
                'status' => 'Inactive',
                'updated_by' => $user->id,
            ]);

            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Product has inventory or history, so it was marked Inactive instead of archived.',
            ]);

            return redirect()->back();
        }

        $product->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Export products as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $products = Product::query()
            ->forOrganization($user)
            ->with(['category:id,code,name', 'uom:id,code,name'])
            ->orderBy('sku')
            ->get();

        $filename = 'products-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Name', 'Category', 'Type', 'UOM', 'Barcode', 'Status']);

            foreach ($products as $product) {
                fputcsv($handle, [
                    $product->sku,
                    $product->name,
                    $product->category?->code,
                    $product->type,
                    $product->uom?->code,
                    $product->barcode,
                    $product->status,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Import products from CSV.
     */
    public function import(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded file.',
            ]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'The CSV file is empty.',
            ]);
        }

        $headerMap = $this->normalizeImportHeader($header);
        $required = ['sku', 'name', 'category', 'type', 'uom'];
        foreach ($required as $column) {
            if (! array_key_exists($column, $headerMap)) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'file' => "Missing required column: {$column}.",
                ]);
            }
        }

        $categories = ProductCategory::query()
            ->forOrganization($user)
            ->where('status', 'Active')
            ->get()
            ->keyBy(fn (ProductCategory $category) => strtoupper($category->code));

        $uoms = UnitOfMeasure::query()
            ->forOrganization($user)
            ->where('status', 'Active')
            ->get()
            ->keyBy(fn (UnitOfMeasure $uom) => strtoupper($uom->code));

        $created = 0;
        $updated = 0;
        $rowNumber = 1;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $sku = strtoupper(trim((string) ($row[$headerMap['sku']] ?? '')));
            $name = trim((string) ($row[$headerMap['name']] ?? ''));
            $categoryCode = strtoupper(trim((string) ($row[$headerMap['category']] ?? '')));
            $type = trim((string) ($row[$headerMap['type']] ?? ''));
            $uomCode = strtoupper(trim((string) ($row[$headerMap['uom']] ?? '')));
            $barcode = isset($headerMap['barcode']) ? trim((string) ($row[$headerMap['barcode']] ?? '')) : '';
            $status = isset($headerMap['status']) ? trim((string) ($row[$headerMap['status']] ?? 'Active')) : 'Active';

            if ($sku === '' || $name === '' || $categoryCode === '' || $type === '' || $uomCode === '') {
                $errors[] = "Row {$rowNumber}: SKU, Name, Category, Type, and UOM are required.";

                continue;
            }

            if (! in_array($type, Product::TYPES, true)) {
                $errors[] = "Row {$rowNumber}: invalid product type \"{$type}\".";

                continue;
            }

            if (! in_array($status, Product::STATUSES, true)) {
                $errors[] = "Row {$rowNumber}: invalid status \"{$status}\".";

                continue;
            }

            $category = $categories->get($categoryCode);
            $uom = $uoms->get($uomCode);

            if (! $category) {
                $errors[] = "Row {$rowNumber}: active category \"{$categoryCode}\" was not found.";

                continue;
            }

            if (! $uom) {
                $errors[] = "Row {$rowNumber}: active UOM \"{$uomCode}\" was not found.";

                continue;
            }

            $barcodeValue = $barcode !== '' ? $barcode : null;
            if ($barcodeValue !== null) {
                $barcodeTaken = Product::query()
                    ->forOrganization($user)
                    ->where('barcode', $barcodeValue)
                    ->where('sku', '!=', $sku)
                    ->exists();

                if ($barcodeTaken) {
                    $errors[] = "Row {$rowNumber}: barcode \"{$barcodeValue}\" is already used.";

                    continue;
                }
            }

            $existing = Product::query()
                ->forOrganization($user)
                ->where('sku', $sku)
                ->first();

            $payload = [
                'name' => $name,
                'category_id' => $category->id,
                'uom_id' => $uom->id,
                'type' => $type,
                'barcode' => $barcodeValue,
                'status' => $status,
                'updated_by' => $user->id,
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Product::create([
                    ...$payload,
                    'organization_id' => $user->organization_id,
                    'sku' => $sku,
                    'created_by' => $user->id,
                ]);
                $created++;
            }
        }

        fclose($handle);

        if ($errors !== []) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Import completed with errors. '.$errors[0].(count($errors) > 1 ? ' (+'.(count($errors) - 1).' more)' : ''),
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Import complete: {$created} created, {$updated} updated.",
            ]);
        }

        return redirect()->back();
    }

    /**
     * Perform a bulk action on selected products.
     */
    public function bulk(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'assign_category', 'assign_warehouse', 'export', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'category_id' => [
                'required_if:action,assign_category',
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at'),
            ],
            'default_warehouse_id' => [
                'required_if:action,assign_warehouse',
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $permission = match ($validated['action']) {
            'activate', 'deactivate', 'assign_category', 'assign_warehouse' => 'products.update',
            'export' => 'products.export',
            'delete' => 'products.delete',
        };

        if (! $user->hasPermission($permission)) {
            abort(403);
        }

        $products = Product::query()
            ->forOrganization($user)
            ->whereIn('id', $validated['ids'])
            ->get();

        if ($products->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No matching products found for this organization.',
            ]);

            return redirect()->back();
        }

        return match ($validated['action']) {
            'activate' => $this->bulkUpdateStatus($products, 'Active', 'Products activated successfully.'),
            'deactivate' => $this->bulkUpdateStatus($products, 'Inactive', 'Products deactivated successfully.'),
            'assign_category' => $this->bulkAssignCategory($products, (int) $validated['category_id']),
            'assign_warehouse' => $this->bulkAssignWarehouse($products, (int) $validated['default_warehouse_id']),
            'export' => $this->bulkExport($products),
            'delete' => $this->bulkDelete($products, $user),
        };
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function bulkUpdateStatus($products, string $status, string $message)
    {
        Product::query()
            ->whereIn('id', $products->pluck('id'))
            ->update(['status' => $status]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return redirect()->back();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function bulkAssignCategory($products, int $categoryId)
    {
        Product::query()
            ->whereIn('id', $products->pluck('id'))
            ->update(['category_id' => $categoryId]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Category assigned to selected products.',
        ]);

        return redirect()->back();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function bulkAssignWarehouse($products, int $warehouseId)
    {
        Product::query()
            ->whereIn('id', $products->pluck('id'))
            ->update(['default_warehouse_id' => $warehouseId]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Warehouse assigned to selected products.',
        ]);

        return redirect()->back();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function bulkDelete($products, $user)
    {
        $archived = 0;
        $deactivated = 0;

        foreach ($products as $product) {
            if ($product->hasBlockingDependencies()) {
                $product->update([
                    'status' => 'Inactive',
                    'updated_by' => $user->id,
                ]);
                $deactivated++;

                continue;
            }

            $product->delete();
            $archived++;
        }

        $message = match (true) {
            $archived > 0 && $deactivated > 0 => "{$archived} product(s) archived, {$deactivated} marked Inactive due to inventory or history.",
            $deactivated > 0 => "{$deactivated} product(s) marked Inactive due to inventory or history.",
            default => 'Selected products archived successfully.',
        };

        Inertia::flash('toast', [
            'type' => $deactivated > 0 && $archived === 0 ? 'warning' : 'success',
            'message' => $message,
        ]);

        return redirect()->back();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function bulkExport($products): StreamedResponse
    {
        $products->loadMissing(['category:id,code,name', 'uom:id,code,name', 'defaultWarehouse:id,code,name']);

        $filename = 'products-selected-'.now()->format('Y-m-d-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'sku',
                'name',
                'type',
                'status',
                'category',
                'uom',
                'warehouse',
                'brand',
                'manufacturer',
                'opening_stock',
                'opening_cost',
            ]);

            foreach ($products as $product) {
                fputcsv($handle, [
                    $product->sku,
                    $product->name,
                    $product->type,
                    $product->status,
                    $product->category?->name,
                    $product->uom?->code,
                    $product->defaultWarehouse?->code,
                    $product->brand,
                    $product->manufacturer,
                    $product->opening_stock,
                    $product->opening_cost,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return array{categories: mixed, uoms: mixed, types: list<string>, statuses: list<string>, warehouses: mixed, valuationMethods: list<string>, taxClasses: list<string>}
     */
    private function formOptions($user, ?Product $product = null): array
    {
        $categories = ProductCategory::query()
            ->forOrganization($user)
            ->where(function ($query) use ($product) {
                $query->where('status', 'Active');
                if ($product) {
                    $query->orWhere('id', $product->category_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        $uoms = UnitOfMeasure::query()
            ->forOrganization($user)
            ->where(function ($query) use ($product) {
                $query->where('status', 'Active');
                if ($product) {
                    $query->orWhere('id', $product->uom_id)
                        ->orWhere('id', $product->purchase_uom_id)
                        ->orWhere('id', $product->sales_uom_id)
                        ->orWhere('id', $product->manufacturing_uom_id);
                }
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'status']);

        $warehouses = Warehouse::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return [
            'categories' => $categories,
            'uoms' => $uoms,
            'types' => Product::TYPES,
            'statuses' => Product::STATUSES,
            'warehouses' => $warehouses,
            'valuationMethods' => Product::VALUATION_METHODS,
            'taxClasses' => Product::TAX_CLASSES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request, $user, ?Product $product = null): array
    {
        foreach (Product::BOOLEAN_FIELDS as $field) {
            if ($request->has($field)) {
                $request->merge([$field => $request->boolean($field)]);
            }
        }

        $request->merge([
            'sku' => strtoupper(trim((string) $request->input('sku', ''))),
            'barcode' => $request->filled('barcode') ? trim((string) $request->input('barcode')) : null,
            'description' => $request->filled('description') ? $request->input('description') : null,
            'supplier_sku' => $request->filled('supplier_sku') ? $request->input('supplier_sku') : null,
            'preferred_supplier_id' => $request->filled('preferred_supplier_id') ? $request->input('preferred_supplier_id') : null,
            'purchase_uom_id' => $request->filled('purchase_uom_id') ? $request->input('purchase_uom_id') : null,
            'sales_uom_id' => $request->filled('sales_uom_id') ? $request->input('sales_uom_id') : null,
            'manufacturing_uom_id' => $request->filled('manufacturing_uom_id') ? $request->input('manufacturing_uom_id') : null,
            'default_warehouse_id' => $request->filled('default_warehouse_id') ? $request->input('default_warehouse_id') : null,
            'opening_stock' => $request->filled('opening_stock') ? $request->input('opening_stock') : null,
            'opening_cost' => $request->filled('opening_cost') ? $request->input('opening_cost') : null,
            'dimensions' => $request->filled('dimensions') ? $request->input('dimensions') : null,
            'reorder_level' => $request->filled('reorder_level') ? $request->input('reorder_level') : null,
            'minimum_stock' => $request->filled('minimum_stock') ? $request->input('minimum_stock') : null,
            'maximum_stock' => $request->filled('maximum_stock') ? $request->input('maximum_stock') : null,
            'safety_stock' => $request->filled('safety_stock') ? $request->input('safety_stock') : null,
            'economic_order_quantity' => $request->filled('economic_order_quantity') ? $request->input('economic_order_quantity') : null,
            'lead_time_days' => $request->filled('lead_time_days') ? $request->input('lead_time_days') : null,
            'shelf_life_days' => $request->filled('shelf_life_days') ? $request->input('shelf_life_days') : null,
            'purchase_price' => $request->filled('purchase_price') ? $request->input('purchase_price') : null,
            'selling_price' => $request->filled('selling_price') ? $request->input('selling_price') : null,
            'default_discount' => $request->filled('default_discount') ? $request->input('default_discount') : null,
            'tax_rate' => $request->filled('tax_rate') ? $request->input('tax_rate') : null,
            'tax_class' => $request->filled('tax_class') && $request->input('tax_class') !== 'None' ? $request->input('tax_class') : null,
            'hsn_sac_code' => $request->filled('hsn_sac_code') ? $request->input('hsn_sac_code') : null,
            'weight' => $request->filled('weight') ? $request->input('weight') : null,
            'brand' => $request->filled('brand') ? $request->input('brand') : null,
            'manufacturer' => $request->filled('manufacturer') ? $request->input('manufacturer') : null,
            'country_of_origin' => $request->filled('country_of_origin') ? $request->input('country_of_origin') : null,
            'abc_classification' => $request->filled('abc_classification') ? $request->input('abc_classification') : null,
            'xyz_classification' => $request->filled('xyz_classification') ? $request->input('xyz_classification') : null,
            'notes' => $request->filled('notes') ? $request->input('notes') : null,
        ]);

        $uniqueSku = Rule::unique('products', 'sku')
            ->where(fn ($query) => $query
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at'));

        $uniqueBarcode = Rule::unique('products', 'barcode')
            ->where(fn ($query) => $query
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at'));

        if ($product) {
            $uniqueSku->ignore($product->id);
            $uniqueBarcode->ignore($product->id);
        }

        $activeCategory = Rule::exists('product_categories', 'id')
            ->where('organization_id', $user->organization_id)
            ->where('status', 'Active')
            ->whereNull('deleted_at');

        $activeUom = Rule::exists('units_of_measure', 'id')
            ->where('organization_id', $user->organization_id)
            ->where('status', 'Active')
            ->whereNull('deleted_at');

        if ($product) {
            // Allow keeping currently assigned inactive category/UOM.
            if ((int) $request->input('category_id') === (int) $product->category_id) {
                $activeCategory = Rule::exists('product_categories', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at');
            }
            if ((int) $request->input('uom_id') === (int) $product->uom_id) {
                $activeUom = Rule::exists('units_of_measure', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at');
            }
        }

        $uomExistsRule = Rule::exists('units_of_measure', 'id')
            ->where('organization_id', $user->organization_id)
            ->whereNull('deleted_at');

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', $uniqueSku],
            'barcode' => ['nullable', 'string', 'max:100', $uniqueBarcode],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', $activeCategory],
            'uom_id' => ['required', 'integer', $activeUom],
            'type' => ['required', Rule::in(Product::TYPES)],
            'status' => ['required', Rule::in(Product::STATUSES)],
            // Inventory
            'track_inventory' => ['sometimes', 'boolean'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'economic_order_quantity' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'inventory_valuation_method' => ['sometimes', 'string', Rule::in(Product::VALUATION_METHODS)],
            'default_warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'opening_stock' => ['nullable', 'numeric', 'min:0'],
            'opening_cost' => ['nullable', 'numeric', 'min:0'],
            // Manufacturing
            'make_to_stock' => ['sometimes', 'boolean'],
            'make_to_order' => ['sometimes', 'boolean'],
            'bom_required' => ['sometimes', 'boolean'],
            'routing_required' => ['sometimes', 'boolean'],
            'backflush_material' => ['sometimes', 'boolean'],
            'manufacturing_uom_id' => ['nullable', 'integer', $uomExistsRule],
            // Traceability
            'lot_tracking' => ['sometimes', 'boolean'],
            'serial_tracking' => ['sometimes', 'boolean'],
            'expiry_tracking' => ['sometimes', 'boolean'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            // Purchasing
            'preferred_supplier_id' => ['nullable', 'integer'],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'purchase_uom_id' => ['nullable', 'integer', $uomExistsRule],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            // Sales
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'sales_uom_id' => ['nullable', 'integer', $uomExistsRule],
            'tax_class' => ['nullable', 'string', 'max:50'],
            'hsn_sac_code' => ['nullable', 'string', 'max:30'],
            'default_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'string', 'max:100'],
            // Media
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'datasheet' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'safety_sheet' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'technical_drawing' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remove_image' => ['sometimes', 'boolean'],
            'remove_datasheet' => ['sometimes', 'boolean'],
            'remove_safety_sheet' => ['sometimes', 'boolean'],
            'remove_technical_drawing' => ['sometimes', 'boolean'],
            // Additional
            'brand' => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'abc_classification' => ['nullable', 'string', Rule::in(Product::ABC_CLASSES)],
            'xyz_classification' => ['nullable', 'string', Rule::in(Product::XYZ_CLASSES)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $lotTracking = (bool) ($validated['lot_tracking'] ?? $product?->lot_tracking ?? false);
        $serialTracking = (bool) ($validated['serial_tracking'] ?? $product?->serial_tracking ?? false);

        if ($lotTracking && $serialTracking) {
            throw ValidationException::withMessages([
                'serial_tracking' => 'Lot tracking and serial tracking cannot both be enabled.',
            ]);
        }

        foreach (Product::BOOLEAN_FIELDS as $field) {
            $validated[$field] = (bool) ($validated[$field] ?? false);
        }

        if (! $validated['track_inventory']) {
            $validated['allow_negative_stock'] = false;
            $validated['default_warehouse_id'] = null;
            $validated['opening_stock'] = 0;
            $validated['opening_cost'] = 0;
        }

        $stockErrors = $this->stockLevelValidationErrors($validated);
        if ($stockErrors !== []) {
            throw ValidationException::withMessages($stockErrors);
        }

        unset(
            $validated['image'],
            $validated['datasheet'],
            $validated['safety_sheet'],
            $validated['technical_drawing'],
            $validated['remove_image'],
            $validated['remove_datasheet'],
            $validated['remove_safety_sheet'],
            $validated['remove_technical_drawing'],
        );

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function stockLevelValidationErrors(array $validated): array
    {
        $maximum = $this->nullableFloat($validated['maximum_stock'] ?? null);
        $minimum = $this->nullableFloat($validated['minimum_stock'] ?? null);
        $safety = $this->nullableFloat($validated['safety_stock'] ?? null);
        $reorder = $this->nullableFloat($validated['reorder_level'] ?? null);

        $errors = [];

        if ($maximum !== null && $minimum !== null && $maximum < $minimum) {
            $errors['maximum_stock'] = 'Maximum stock must be greater than or equal to minimum stock.';
        }

        if ($minimum !== null && $safety !== null && $minimum < $safety) {
            $errors['minimum_stock'] = 'Minimum stock must be greater than or equal to safety stock.';
        }

        if ($safety !== null && $reorder !== null && $safety > $reorder) {
            $errors['safety_stock'] = 'Safety stock must be less than or equal to reorder point.';
        }

        return $errors;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array<string, string|null>
     */
    private function storeMediaFiles(Request $request, ?Product $product = null): array
    {
        $paths = [];

        $map = [
            'image' => 'image_path',
            'datasheet' => 'datasheet_path',
            'safety_sheet' => 'safety_sheet_path',
            'technical_drawing' => 'technical_drawing_path',
        ];

        foreach ($map as $input => $column) {
            $removeKey = 'remove_'.$input;
            $current = $product?->{$column};

            if ($request->hasFile($input)) {
                if ($current) {
                    Storage::disk('public')->delete($current);
                }
                $paths[$column] = $request->file($input)->store('products', 'public');
            } elseif ($request->boolean($removeKey)) {
                if ($current) {
                    Storage::disk('public')->delete($current);
                }
                $paths[$column] = null;
            }
        }

        return $paths;
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function normalizeImportHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $column) {
            $key = strtolower(trim((string) $column));
            $key = str_replace([' ', '-'], '_', $key);
            if ($key !== '') {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function belongsToOrganization($user, Product $product): bool
    {
        return (int) $product->organization_id === (int) $user->organization_id;
    }

    /**
     * Store newly uploaded attachments from the multi-upload array.
     */
    private function storeNewAttachments(Request $request, Product $product, $user): void
    {
        $files = $request->file('new_attachments', []);
        $types = (array) $request->input('new_attachment_types', []);

        if (empty($files)) {
            return;
        }

        foreach ($files as $index => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $type = $types[$index] ?? 'other';
            if (! in_array($type, ProductAttachment::TYPES, true)) {
                $type = 'other';
            }

            $path = $file->store('product-attachments', 'public');

            ProductAttachment::create([
                'product_id' => $product->id,
                'organization_id' => $product->organization_id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'type' => $type,
                'sort_order' => $index,
                'created_by' => $user->id,
            ]);
        }
    }

    protected function postOpeningStock($user, Product $product): void
    {
        if (! $product->track_inventory || (float) $product->opening_stock <= 0) {
            return;
        }

        $warehouseId = $product->default_warehouse_id;
        $warehouse = null;
        if ($warehouseId) {
            $warehouse = Warehouse::find($warehouseId);
        }

        if (! $warehouse) {
            $warehouse = Warehouse::where('plant_id', $user->active_plant_id)
                ->where('status', 'Active')
                ->where('is_default', true)
                ->first() ?? Warehouse::where('plant_id', $user->active_plant_id)->where('status', 'Active')->first();
        }

        if (! $warehouse) {
            return;
        }

        $locationId = $warehouse->default_receiving_location_id ?? $warehouse->default_picking_location_id;
        $location = null;
        if ($locationId) {
            $location = WarehouseLocation::find($locationId);
        }

        if (! $location) {
            $location = WarehouseLocation::where('warehouse_id', $warehouse->id)->where('status', 'Active')->first();
        }

        if (! $location) {
            $location = WarehouseLocation::create([
                'organization_id' => $product->organization_id,
                'warehouse_id' => $warehouse->id,
                'type' => 'Bin',
                'code' => 'DEFAULT',
                'name' => 'Default Location',
                'status' => 'Active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        $exists = InventoryTransaction::where('product_id', $product->id)
            ->where('transaction_type', 'Opening Balance')
            ->exists();

        if ($exists) {
            return;
        }

        $inventory = Inventory::firstOrCreate([
            'organization_id' => $product->organization_id,
            'plant_id' => $warehouse->plant_id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'product_id' => $product->id,
            'lot_number' => '',
            'serial_number' => '',
        ], [
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $before = (float) $inventory->quantity_on_hand;
        $after = $before + (float) $product->opening_stock;

        $inventory->update([
            'quantity_on_hand' => $after,
            'last_movement_at' => now(),
        ]);

        $transactionNo = app(InventoryService::class)->nextTransactionNo((int) $product->organization_id, 'OPN');

        InventoryTransaction::create([
            'organization_id' => $product->organization_id,
            'plant_id' => $warehouse->plant_id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'inventory_id' => $inventory->id,
            'product_id' => $product->id,
            'transaction_type' => 'Opening Balance',
            'quantity' => (float) $product->opening_stock,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'unit_cost' => (float) ($product->opening_cost ?? 0.0),
            'total_cost' => (float) ($product->opening_stock * ($product->opening_cost ?? 0.0)),
            'reference_number' => 'OP-'.$product->sku,
            'notes' => 'Auto-posted opening balance from product creation.',
            'created_by' => $user->id,
            'transaction_no' => $transactionNo,
            'lot_number' => '',
            'serial_number' => '',
            'transacted_at' => now(),
        ]);
    }
}
