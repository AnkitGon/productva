<?php

namespace App\Models;

use Database\Factories\BomItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    /** @use HasFactory<BomItemFactory> */
    use HasFactory;

    protected $fillable = [
        'bom_header_id',
        'component_product_id',
        'quantity',
        'uom_id',
        'scrap_percentage',
        'is_phantom',
        'sequence',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scrap_percentage' => 0,
        'is_phantom' => false,
        'sequence' => 10,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'scrap_percentage' => 'decimal:4',
            'is_phantom' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function bomHeader(): BelongsTo
    {
        return $this->belongsTo(BomHeader::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Quantity including scrap allowance.
     */
    public function getGrossQuantityAttribute(): string
    {
        $qty = (float) $this->quantity;
        $scrap = (float) $this->scrap_percentage;
        $gross = $qty * (1 + ($scrap / 100));

        return number_format($gross, 4, '.', '');
    }
}
