<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActivePlant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use BelongsToActivePlant, HasFactory, SoftDeletes;

    public const STATUSES = [
        'Active',
        'Inactive',
    ];

    protected $fillable = [
        'organization_id',
        'plant_id',
        'warehouse_type_id',
        'code',
        'name',
        'manager_employee_id',
        'phone',
        'email',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'allow_negative_stock',
        'is_default',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Active',
        'allow_negative_stock' => false,
        'is_default' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_negative_stock' => 'boolean',
            'is_default' => 'boolean',
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

    public function warehouseType(): BelongsTo
    {
        return $this->belongsTo(WarehouseType::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
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
     * Placeholder until inventory, bins, transactions, and production references exist.
     */
    public function hasBlockingDependencies(): bool
    {
        return false;
    }

    public function hasTransactions(): bool
    {
        return false;
    }
}
