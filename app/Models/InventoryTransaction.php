<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActivePlant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use BelongsToActivePlant, HasFactory;

    public const TYPES = [
        'Opening Balance',
        'Adjustment',
        'Goods Receipt',
        'Goods Issue',
        'Transfer In',
        'Transfer Out',
        'Production Consume',
        'Production Output',
        'Cycle Count',
        'Reservation',
        'Reservation Release',
        'Sales Shipment',
        'Purchase Receipt',
    ];

    /**
     * Document number prefixes by transaction type.
     *
     * @var array<string, string>
     */
    public const TYPE_PREFIXES = [
        'Opening Balance' => 'OPN',
        'Adjustment' => 'ADJ',
        'Goods Receipt' => 'GRN',
        'Goods Issue' => 'ISS',
        'Transfer In' => 'TRF',
        'Transfer Out' => 'TRF',
        'Production Consume' => 'PRC',
        'Production Output' => 'PRO',
        'Cycle Count' => 'CYC',
        'Reservation' => 'RSV',
        'Reservation Release' => 'RSR',
        'Sales Shipment' => 'SHP',
        'Purchase Receipt' => 'PUR',
    ];

    protected $fillable = [
        'organization_id',
        'plant_id',
        'inventory_id',
        'product_id',
        'warehouse_id',
        'warehouse_location_id',
        'from_warehouse_id',
        'from_location_id',
        'to_warehouse_id',
        'to_location_id',
        'lot_number',
        'serial_number',
        'transaction_no',
        'transaction_type',
        'reference_type',
        'reference_id',
        'reference_number',
        'quantity',
        'quantity_before',
        'quantity_after',
        'notes',
        'created_by',
        'transacted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'quantity_before' => 'decimal:4',
            'quantity_after' => 'decimal:4',
            'transacted_at' => 'datetime',
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

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'from_location_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'to_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function prefixFor(string $transactionType): string
    {
        return self::TYPE_PREFIXES[$transactionType] ?? 'TXN';
    }
}
