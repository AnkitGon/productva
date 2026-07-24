<?php

namespace App\Models;

use App\Models\Concerns\AutoGeneratesCode;
use App\Models\Concerns\BelongsToActivePlant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseLocation extends Model
{
    use AutoGeneratesCode, BelongsToActivePlant, HasFactory, SoftDeletes;

    public const TYPES = [
        'Zone',
        'Aisle',
        'Rack',
        'Shelf',
        'Bin',
    ];

    public const STATUSES = [
        'Active',
        'Inactive',
    ];

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'parent_id',
        'type',
        'code',
        'name',
        'barcode',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'Zone',
        'status' => 'Active',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'full_path',
    ];

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOrganization(Builder $query, User $user): Builder
    {
        return $query->where($query->getModel()->getTable().'.organization_id', $user->organization_id);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForActivePlant(Builder $query, User $user): Builder
    {
        return $query->whereHas('warehouse', function ($q) use ($user) {
            $q->where('plant_id', $user->active_plant_id);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'parent_id')->orderBy('code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get full breadcrumb path string e.g. "Zone A > Aisle 1 > Rack 01".
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $curr = $this->relationLoaded('parent') ? $this->parent : null;

        // If relation not eager loaded, fetch parents up to root
        if (! $this->relationLoaded('parent') && $this->parent_id) {
            $curr = $this->parent;
        }

        $guard = 0;
        while ($curr && $guard < 10) {
            array_unshift($path, $curr->name);
            $curr = $curr->parent;
            $guard++;
        }

        return implode(' > ', $path);
    }

    /**
     * Check if a given candidate parent ID would create a circular dependency.
     */
    public function isDescendantOf(int $candidateParentId): bool
    {
        if ($this->id === $candidateParentId) {
            return true;
        }

        $parent = self::find($candidateParentId);
        $guard = 0;
        while ($parent && $guard < 10) {
            if ($parent->id === $this->id) {
                return true;
            }
            $parent = $parent->parent;
            $guard++;
        }

        return false;
    }
}
