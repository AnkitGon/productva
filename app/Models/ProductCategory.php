<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'Active',
        'Inactive',
    ];

    protected $fillable = [
        'organization_id',
        'parent_id',
        'code',
        'name',
        'description',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Active',
        'sort_order' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'products_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'parent_id' => 'integer',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function canBeAssignedToProducts(): bool
    {
        return $this->status === 'Active';
    }

    public function hasProducts(): bool
    {
        if (array_key_exists('products_count', $this->attributes)) {
            return (int) $this->attributes['products_count'] > 0;
        }

        return $this->products()->exists();
    }

    public function getProductsCountAttribute(): int
    {
        if (array_key_exists('products_count', $this->attributes)) {
            return (int) $this->attributes['products_count'];
        }

        return $this->products()->count();
    }

    /**
     * Whether assigning $parentId as this category's parent would create a cycle.
     */
    public function wouldCreateCycle(?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ($parentId === (int) $this->id) {
            return true;
        }

        $visited = [];
        $currentId = $parentId;

        while ($currentId !== null) {
            if ($currentId === (int) $this->id) {
                return true;
            }

            if (isset($visited[$currentId])) {
                return true;
            }

            $visited[$currentId] = true;

            $currentId = self::query()
                ->whereKey($currentId)
                ->value('parent_id');

            $currentId = $currentId !== null ? (int) $currentId : null;
        }

        return false;
    }
}
