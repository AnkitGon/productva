<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActivePlant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use BelongsToActivePlant, HasFactory;

    public const STOCK_STATUSES = [
        'In Stock',
        'Low Stock',
        'Out Of Stock',
        'Negative',
    ];

    protected $fillable = [
        'organization_id',
        'plant_id',
        'warehouse_id',
        'warehouse_location_id',
        'product_id',
        'lot_number',
        'serial_number',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_incoming',
        'quantity_outgoing',
        'last_movement_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lot_number' => '',
        'serial_number' => '',
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
        'quantity_incoming' => 0,
        'quantity_outgoing' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'quantity_available',
        'stock_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'quantity_incoming' => 'decimal:4',
            'quantity_outgoing' => 'decimal:4',
            'last_movement_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function getQuantityAvailableAttribute(): string
    {
        return number_format((float) $this->quantity_on_hand - (float) $this->quantity_reserved, 4, '.', '');
    }

    public function getStockStatusAttribute(): string
    {
        $onHand = (float) $this->quantity_on_hand;

        if ($onHand < 0) {
            return 'Negative';
        }

        if ($onHand == 0.0) {
            return 'Out Of Stock';
        }

        $minimum = $this->resolveMinimumLevel();

        if ($minimum !== null && $onHand < $minimum) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    public function resolveMinimumLevel(): ?float
    {
        if (! $this->relationLoaded('product') || ! $this->product) {
            return null;
        }

        $minimum = $this->product->minimum_stock ?? $this->product->reorder_level;

        return $minimum !== null ? (float) $minimum : null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithStockStatusFilters(Builder $query, ?string $status): Builder
    {
        if (! $status) {
            return $query;
        }

        return match ($status) {
            'Negative' => $query->where('quantity_on_hand', '<', 0),
            'Out Of Stock' => $query->where('quantity_on_hand', '=', 0),
            'Low Stock' => $query->where('quantity_on_hand', '>', 0)
                ->whereHas('product', function ($productQuery) {
                    $productQuery->where(function ($q) {
                        $q->where(function ($inner) {
                            $inner->whereNotNull('products.minimum_stock')
                                ->whereColumn('inventories.quantity_on_hand', '<', 'products.minimum_stock');
                        })->orWhere(function ($inner) {
                            $inner->whereNull('products.minimum_stock')
                                ->whereNotNull('products.reorder_level')
                                ->whereColumn('inventories.quantity_on_hand', '<', 'products.reorder_level');
                        });
                    });
                }),
            'In Stock' => $query->where('quantity_on_hand', '>', 0)
                ->where(function ($q) {
                    $q->whereDoesntHave('product', function ($productQuery) {
                        $productQuery->where(function ($inner) {
                            $inner->whereNotNull('minimum_stock')
                                ->whereColumn('inventories.quantity_on_hand', '<', 'products.minimum_stock');
                        })->orWhere(function ($inner) {
                            $inner->whereNull('minimum_stock')
                                ->whereNotNull('reorder_level')
                                ->whereColumn('inventories.quantity_on_hand', '<', 'products.reorder_level');
                        });
                    });
                }),
            default => $query,
        };
    }
}
