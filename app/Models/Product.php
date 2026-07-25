<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'Raw Material',
        'Semi Finished',
        'Finished Good',
        'Packaging',
        'Consumable',
        'Spare Part',
        'Service',
    ];

    public const STATUSES = [
        'Active',
        'Inactive',
        'Obsolete',
    ];

    public const VALUATION_METHODS = [
        'FIFO',
        'LIFO',
        'Weighted Average',
        'Standard Cost',
    ];

    public const ABC_CLASSES = ['A', 'B', 'C'];

    public const XYZ_CLASSES = ['X', 'Y', 'Z'];

    public const TAX_CLASSES = [
        'None',
        'GST 5%',
        'GST 12%',
        'GST 18%',
        'GST 28%',
        'VAT 5%',
        'VAT 10%',
        'VAT 20%',
        'Exempt',
        'Zero Rated',
    ];

    public const BOOLEAN_FIELDS = [
        'track_inventory',
        'allow_negative_stock',
        'make_to_stock',
        'make_to_order',
        'bom_required',
        'routing_required',
        'backflush_material',
        'lot_tracking',
        'serial_tracking',
        'expiry_tracking',
    ];

    protected $fillable = [
        'organization_id',
        'sku',
        'barcode',
        'name',
        'description',
        'category_id',
        'uom_id',
        'type',
        'status',
        // Inventory
        'track_inventory',
        'allow_negative_stock',
        'reorder_level',
        'minimum_stock',
        'maximum_stock',
        'safety_stock',
        'lead_time_days',
        'inventory_valuation_method',
        'default_warehouse_id',
        'opening_stock',
        'opening_cost',
        'economic_order_quantity',
        // Manufacturing
        'make_to_stock',
        'make_to_order',
        'bom_required',
        'routing_required',
        'backflush_material',
        'manufacturing_uom_id',
        // Traceability
        'lot_tracking',
        'serial_tracking',
        'expiry_tracking',
        'shelf_life_days',
        // Purchasing
        'preferred_supplier_id',
        'supplier_sku',
        'purchase_uom_id',
        'purchase_price',
        // Sales
        'selling_price',
        'sales_uom_id',
        'tax_class',
        'hsn_sac_code',
        'default_discount',
        'tax_rate',
        'weight',
        'dimensions',
        // Media
        'image_path',
        'datasheet_path',
        'safety_sheet_path',
        'technical_drawing_path',
        // Additional
        'brand',
        'manufacturer',
        'country_of_origin',
        'abc_classification',
        'xyz_classification',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Active',
        'inventory_valuation_method' => 'FIFO',
        'track_inventory' => true,
        'allow_negative_stock' => false,
        'make_to_stock' => false,
        'make_to_order' => false,
        'bom_required' => false,
        'routing_required' => false,
        'backflush_material' => false,
        'lot_tracking' => false,
        'serial_tracking' => false,
        'expiry_tracking' => false,
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'image_url',
        'datasheet_url',
        'safety_sheet_url',
        'technical_drawing_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'make_to_stock' => 'boolean',
            'make_to_order' => 'boolean',
            'bom_required' => 'boolean',
            'routing_required' => 'boolean',
            'backflush_material' => 'boolean',
            'lot_tracking' => 'boolean',
            'serial_tracking' => 'boolean',
            'expiry_tracking' => 'boolean',
            'reorder_level' => 'decimal:4',
            'minimum_stock' => 'decimal:4',
            'maximum_stock' => 'decimal:4',
            'safety_stock' => 'decimal:4',
            'economic_order_quantity' => 'decimal:4',
            'opening_stock' => 'decimal:4',
            'opening_cost' => 'decimal:4',
            'lead_time_days' => 'integer',
            'shelf_life_days' => 'integer',
            'purchase_price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'default_discount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'weight' => 'decimal:4',
            'preferred_supplier_id' => 'integer',
            'default_warehouse_id' => 'integer',
            'manufacturing_uom_id' => 'integer',
            'sales_uom_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOrganization(Builder $query, User $user): Builder
    {
        return $query->where($query->getModel()->getTable().'.organization_id', $user->organization_id);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_uom_id');
    }

    public function salesUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'sales_uom_id');
    }

    public function manufacturingUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'manufacturing_uom_id');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProductAttachment::class)->orderBy('type')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->publicStorageUrl($this->image_path);
    }

    public function getDatasheetUrlAttribute(): ?string
    {
        return $this->publicStorageUrl($this->datasheet_path);
    }

    public function getSafetySheetUrlAttribute(): ?string
    {
        return $this->publicStorageUrl($this->safety_sheet_path);
    }

    public function getTechnicalDrawingUrlAttribute(): ?string
    {
        return $this->publicStorageUrl($this->technical_drawing_path);
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Root-relative so images/files work regardless of APP_URL / local domain.
        return '/storage/'.ltrim($path, '/');
    }

    public function bomHeaders(): HasMany
    {
        return $this->hasMany(BomHeader::class);
    }

    public function routingHeaders(): HasMany
    {
        return $this->hasMany(RoutingHeader::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Whether this product is referenced by inventory, BOMs, routings, or production.
     */
    public function hasBlockingDependencies(): bool
    {
        if ($this->bomHeaders()->exists()) {
            return true;
        }

        if (BomItem::query()->where('component_product_id', $this->id)->exists()) {
            return true;
        }

        if ($this->routingHeaders()->exists()) {
            return true;
        }

        if ($this->inventories()->where('quantity_on_hand', '>', 0)->exists()) {
            return true;
        }

        if (InventoryTransaction::query()->where('product_id', $this->id)->exists()) {
            return true;
        }

        return false;
    }

    public function markInactive(): void
    {
        $this->update(['status' => 'Inactive']);
    }
}
