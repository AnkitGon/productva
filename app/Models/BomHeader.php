<?php

namespace App\Models;

use Database\Factories\BomHeaderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BomHeader extends Model
{
    /** @use HasFactory<BomHeaderFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'Draft',
        'Active',
        'Obsolete',
    ];

    protected $fillable = [
        'organization_id',
        'product_id',
        'version',
        'is_default',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'status' => 'Draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class)->orderBy('sequence')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function explode(float $quantity = 1.0): array
    {
        $results = [];
        $this->explodeRecursive($this, $quantity, $results);

        return array_values($results);
    }

    protected function explodeRecursive(BomHeader $bom, float $parentQty, array &$results): void
    {
        foreach ($bom->items()->with('component')->get() as $item) {
            $product = $item->component;
            if (! $product) {
                continue;
            }

            $itemQty = (float) $item->quantity;
            $requiredQty = $itemQty * $parentQty;

            if ((int) $item->uom_id !== (int) $product->uom_id) {
                $itemUom = UnitOfMeasure::find($item->uom_id);
                if ($itemUom) {
                    $requiredQty = $itemUom->convertToBase($requiredQty);
                }
            }

            if ($item->is_phantom) {
                $subBom = self::where('product_id', $product->id)
                    ->where('status', 'Active')
                    ->first();

                if ($subBom) {
                    $this->explodeRecursive($subBom, $requiredQty, $results);

                    continue;
                }
            }

            $key = $product->id.'_'.$product->uom_id;
            if (isset($results[$key])) {
                $results[$key]['quantity'] += $requiredQty;
            } else {
                $results[$key] = [
                    'product_id' => $product->id,
                    'product' => $product,
                    'quantity' => $requiredQty,
                    'uom_id' => $product->uom_id,
                ];
            }
        }
    }
}
