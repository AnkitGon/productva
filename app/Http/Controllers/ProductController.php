<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
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

        if ($request->has('track_inventory') && $request->input('track_inventory') !== '' && $request->input('track_inventory') !== 'all') {
            $query->where('track_inventory', $request->boolean('track_inventory'));
        }

        if ($request->has('lot_tracking') && $request->input('lot_tracking') !== '' && $request->input('lot_tracking') !== 'all') {
            $query->where('lot_tracking', $request->boolean('lot_tracking'));
        }

        if ($request->has('serial_tracking') && $request->input('serial_tracking') !== '' && $request->input('serial_tracking') !== 'all') {
            $query->where('serial_tracking', $request->boolean('serial_tracking'));
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

        return Inertia::render('products/index', [
            'products' => $query->paginate($perPage)->withQueryString(),
            'categories' => $categories,
            'types' => Product::TYPES,
            'statuses' => Product::STATUSES,
            'filters' => $request->only([
                'search',
                'status',
                'type',
                'category_id',
                'track_inventory',
                'lot_tracking',
                'serial_tracking',
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

        Product::create([
            ...$validated,
            ...$paths,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

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

        $product->load(['category:id,code,name,status', 'uom:id,code,name,symbol,status', 'purchaseUom:id,code,name,symbol,status']);

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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product updated successfully.',
        ]);

        return redirect()->route('products.index');
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
     * @return array{categories: mixed, uoms: mixed, types: list<string>, statuses: list<string>}
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
                        ->orWhere('id', $product->purchase_uom_id);
                }
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'status']);

        return [
            'categories' => $categories,
            'uoms' => $uoms,
            'types' => Product::TYPES,
            'statuses' => Product::STATUSES,
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
            'dimensions' => $request->filled('dimensions') ? $request->input('dimensions') : null,
            'reorder_level' => $request->filled('reorder_level') ? $request->input('reorder_level') : null,
            'minimum_stock' => $request->filled('minimum_stock') ? $request->input('minimum_stock') : null,
            'maximum_stock' => $request->filled('maximum_stock') ? $request->input('maximum_stock') : null,
            'safety_stock' => $request->filled('safety_stock') ? $request->input('safety_stock') : null,
            'lead_time_days' => $request->filled('lead_time_days') ? $request->input('lead_time_days') : null,
            'purchase_price' => $request->filled('purchase_price') ? $request->input('purchase_price') : null,
            'selling_price' => $request->filled('selling_price') ? $request->input('selling_price') : null,
            'tax_rate' => $request->filled('tax_rate') ? $request->input('tax_rate') : null,
            'weight' => $request->filled('weight') ? $request->input('weight') : null,
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

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', $uniqueSku],
            'barcode' => ['nullable', 'string', 'max:100', $uniqueBarcode],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', $activeCategory],
            'uom_id' => ['required', 'integer', $activeUom],
            'type' => ['required', Rule::in(Product::TYPES)],
            'status' => ['required', Rule::in(Product::STATUSES)],
            'track_inventory' => ['sometimes', 'boolean'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'make_to_stock' => ['sometimes', 'boolean'],
            'make_to_order' => ['sometimes', 'boolean'],
            'bom_required' => ['sometimes', 'boolean'],
            'routing_required' => ['sometimes', 'boolean'],
            'lot_tracking' => ['sometimes', 'boolean'],
            'serial_tracking' => ['sometimes', 'boolean'],
            'expiry_tracking' => ['sometimes', 'boolean'],
            'preferred_supplier_id' => ['nullable', 'integer'],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'purchase_uom_id' => [
                'nullable',
                'integer',
                Rule::exists('units_of_measure', 'id')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'datasheet' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'safety_sheet' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'technical_drawing' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remove_image' => ['sometimes', 'boolean'],
            'remove_datasheet' => ['sometimes', 'boolean'],
            'remove_safety_sheet' => ['sometimes', 'boolean'],
            'remove_technical_drawing' => ['sometimes', 'boolean'],
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
}
