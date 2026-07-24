<?php

namespace App\Models;

use App\Models\Concerns\AutoGeneratesCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseType extends Model
{
    use AutoGeneratesCode, HasFactory, SoftDeletes;

    public const STATUSES = [
        'Active',
        'Inactive',
    ];

    /**
     * @var list<array{code: string, name: string, description: string}>
     */
    public const DEFAULTS = [
        ['code' => 'RAW', 'name' => 'Raw Material', 'description' => 'Purchased materials awaiting production'],
        ['code' => 'WIP', 'name' => 'Work In Progress', 'description' => 'Production / semi-finished storage'],
        ['code' => 'FG', 'name' => 'Finished Goods', 'description' => 'Sellable finished products'],
        ['code' => 'PACK', 'name' => 'Packaging', 'description' => 'Boxes, labels, cartons, pallets'],
        ['code' => 'RET', 'name' => 'Returns', 'description' => 'Customer or supplier returns'],
        ['code' => 'QUAR', 'name' => 'Quarantine', 'description' => 'Quality hold / inspection'],
        ['code' => 'SCRAP', 'name' => 'Scrap', 'description' => 'Scrap and waste'],
        ['code' => 'TRANSIT', 'name' => 'Transit', 'description' => 'In-transit stock'],
    ];

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Active',
    ];

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

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function canBeAssignedToWarehouses(): bool
    {
        return $this->status === 'Active';
    }

    public static function ensureDefaultsFor(int $organizationId): void
    {
        foreach (self::DEFAULTS as $default) {
            self::query()->firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'code' => $default['code'],
                ],
                [
                    'name' => $default['name'],
                    'description' => $default['description'],
                    'status' => 'Active',
                ]
            );
        }
    }
}
