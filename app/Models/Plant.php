<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'slug',
        'description',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'manager_id',
        'status',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (Plant $plant) {
            // If the plant is marked as default, ensure all other plants in the same organization are not default.
            if ($plant->is_default) {
                static::withoutEvents(function () use ($plant) {
                    static::where('organization_id', $plant->organization_id)
                        ->where('id', '!=', $plant->id)
                        ->update(['is_default' => false]);
                });
            }
        });
    }

    /**
     * Get the organization that owns the plant.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the plant manager employee.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Child relationships
    // ──────────────────────────────────────────────────────────────────────────

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function workCenters(): HasMany
    {
        return $this->hasMany(WorkCenter::class);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function routingHeaders(): HasMany
    {
        return $this->hasMany(RoutingHeader::class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Business rules
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns true if the plant is referenced by any child resource in the system.
     * A plant must NEVER be deleted once it is attached or referenced anywhere.
     */
    public function hasBlockingDependencies(): bool
    {
        return $this->warehouses()->exists()
            || $this->departments()->exists()
            || $this->employees()->exists()
            || $this->workCenters()->exists()
            || $this->machines()->exists()
            || $this->shifts()->exists()
            || $this->inventories()->exists()
            || $this->routingHeaders()->exists()
            || User::where('active_plant_id', $this->id)->exists();
    }
}
