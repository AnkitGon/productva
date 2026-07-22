<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of product categories for the organization.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = ProductCategory::query()
            ->forOrganization($user)
            ->with(['parent:id,code,name']);

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

        if ($request->filled('parent_id')) {
            if ($request->parent_id === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        $allowedSorts = ['code', 'name', 'sort_order', 'status', 'created_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSorts, true) ? $request->get('sort_by') : 'sort_order';
        $sortDir = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir)->orderBy('name');

        $parentOptions = ProductCategory::query()
            ->forOrganization($user)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'parent_id']);

        return Inertia::render('product-categories/index', [
            'categories' => $query->paginate($perPage)->withQueryString(),
            'parentOptions' => $parentOptions,
            'statuses' => ProductCategory::STATUSES,
            'filters' => $request->only([
                'search',
                'status',
                'parent_id',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        ]);
    }

    /**
     * Store a newly created product category.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            abort(403);
        }

        $validated = $this->validateCategory($request, $user);

        ProductCategory::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product category created successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified product category.
     */
    public function update(Request $request, ProductCategory $productCategory)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $productCategory)) {
            abort(403);
        }

        $validated = $this->validateCategory($request, $user, $productCategory);

        $productCategory->update([
            ...$validated,
            'updated_by' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product category updated successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * Archive (soft delete) the specified product category.
     */
    public function destroy(Request $request, ProductCategory $productCategory)
    {
        $user = $request->user();
        if (! $user || ! $this->belongsToOrganization($user, $productCategory)) {
            abort(403);
        }

        if ($productCategory->hasProducts()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot archive this category while products are assigned to it.',
            ]);

            return redirect()->back();
        }

        $productCategory->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product category archived successfully.',
        ]);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, $user, ?ProductCategory $productCategory = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'parent_id' => $request->filled('parent_id') ? $request->input('parent_id') : null,
            'description' => $request->filled('description') ? $request->input('description') : null,
            'sort_order' => $request->filled('sort_order') ? $request->input('sort_order') : 0,
        ]);

        $uniqueCode = Rule::unique('product_categories', 'code')
            ->where(fn ($query) => $query
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at'));

        if ($productCategory) {
            $uniqueCode->ignore($productCategory->id);
        }

        $parentRules = [
            'nullable',
            'integer',
            Rule::exists('product_categories', 'id')
                ->where('organization_id', $user->organization_id)
                ->whereNull('deleted_at'),
        ];

        if ($productCategory) {
            $parentRules[] = Rule::notIn([(string) $productCategory->id]);
        }

        $validated = $request->validate([
            'parent_id' => $parentRules,
            'code' => ['required', 'string', 'max:20', $uniqueCode],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'status' => ['required', Rule::in(ProductCategory::STATUSES)],
        ], [
            'parent_id.not_in' => 'A category cannot be its own parent.',
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        $validated['parent_id'] = $parentId;
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($productCategory && $productCategory->wouldCreateCycle($parentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent would create a circular reference.',
            ]);
        }

        return $validated;
    }

    private function belongsToOrganization($user, ProductCategory $productCategory): bool
    {
        return (int) $productCategory->organization_id === (int) $user->organization_id;
    }
}
