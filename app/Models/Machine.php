<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActivePlant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use BelongsToActivePlant, HasFactory, SoftDeletes;

    public const STATUSES = [
        'Active',
        'Idle',
        'Running',
        'Maintenance',
        'Breakdown',
        'Retired',
    ];

    public const ASSIGNABLE_STATUSES = [
        'Active',
        'Idle',
        'Running',
    ];

    protected $fillable = [
        'organization_id',
        'plant_id',
        'department_id',
        'work_center_id',
        'code',
        'name',
        'manufacturer',
        'model',
        'serial_number',
        'asset_tag',
        'installation_date',
        'purchase_date',
        'capacity',
        'capacity_unit',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'installation_date' => 'date',
            'purchase_date' => 'date',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canBeAssignedToProductionOrders(): bool
    {
        return in_array($this->status, self::ASSIGNABLE_STATUSES, true);
    }

    /**
     * Placeholder until production history exists.
     */
    public function hasProductionHistory(): bool
    {
        return false;
    }
}
