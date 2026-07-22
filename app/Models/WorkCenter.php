<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActivePlant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenter extends Model
{
    use BelongsToActivePlant, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'plant_id',
        'department_id',
        'code',
        'name',
        'description',
        'supervisor_employee_id',
        'capacity',
        'capacity_uom',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Active',
    ];

    protected $appends = ['machines_count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
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

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
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
     * Placeholder until the Machines module exists.
     */
    public function getMachinesCountAttribute(): int
    {
        if (array_key_exists('machines_count', $this->attributes)) {
            return (int) $this->attributes['machines_count'];
        }

        return 0;
    }

    public function hasAssignedMachines(): bool
    {
        return $this->machines_count > 0;
    }

    public function canReceiveProductionOrders(): bool
    {
        return $this->status === 'Active';
    }
}
