<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOfMeasure extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'units_of_measure';

    public const TYPES = [
        'Count',
        'Weight',
        'Length',
        'Volume',
        'Area',
        'Time',
    ];

    public const STATUSES = [
        'Active',
        'Inactive',
    ];

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'symbol',
        'type',
        'decimal_places',
        'status',
        'description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Active',
        'decimal_places' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'uom_id');
    }

    public function canBeAssignedToProducts(): bool
    {
        return $this->status === 'Active';
    }

    public function isReferencedByProduct(): bool
    {
        return Product::query()
            ->where(function ($query) {
                $query->where('uom_id', $this->id)
                    ->orWhere('purchase_uom_id', $this->id);
            })
            ->exists();
    }
}
