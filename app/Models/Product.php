<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    public const BOOLEAN_FIELDS = [
        'track_inventory',
        'allow_negative_stock',
        'make_to_stock',
        'make_to_order',
        'bom_required',
        'routing_required',
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
        'track_inventory',
        'allow_negative_stock',
        'reorder_level',
        'minimum_stock',
        'maximum_stock',
        'safety_stock',
        'lead_time_days',
        'make_to_stock',
        'make_to_order',
        'bom_required',
        'routing_required',
        'lot_tracking',
        'serial_tracking',
        'expiry_tracking',
        'preferred_supplier_id',
        'supplier_sku',
        'purchase_uom_id',
        'purchase_price',
        'selling_price',
        'tax_rate',
        'weight',
        'dimensions',
        'image_path',
        'datasheet_path',
        'safety_sheet_path',
        'technical_drawing_path',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Active',
        'track_inventory' => true,
        'allow_negative_stock' => false,
        'make_to_stock' => false,
        'make_to_order' => false,
        'bom_required' => false,
        'routing_required' => false,
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
            'lot_tracking' => 'boolean',
            'serial_tracking' => 'boolean',
            'expiry_tracking' => 'boolean',
            'reorder_level' => 'decimal:4',
            'minimum_stock' => 'decimal:4',
            'maximum_stock' => 'decimal:4',
            'safety_stock' => 'decimal:4',
            'lead_time_days' => 'integer',
            'purchase_price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'weight' => 'decimal:4',
            'preferred_supplier_id' => 'integer',
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

    /**
     * Placeholder until inventory, BOM, and production modules exist.
     */
    public function hasBlockingDependencies(): bool
    {
        return false;
    }

    public function markInactive(): void
    {
        $this->update(['status' => 'Inactive']);
    }
}
