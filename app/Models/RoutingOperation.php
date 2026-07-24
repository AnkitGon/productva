<?php

namespace App\Models;

use Database\Factories\RoutingOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingOperation extends Model
{
    /** @use HasFactory<RoutingOperationFactory> */
    use HasFactory;

    protected $fillable = [
        'routing_header_id',
        'sequence',
        'operation_id',
        'work_center_id',
        'machine_id',
        'setup_time_minutes',
        'run_time_per_unit',
        'labor_time',
        'queue_time',
        'move_time',
        'wait_time',
        'overlap_percent',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'setup_time_minutes' => 0,
        'labor_time' => 0,
        'queue_time' => 0,
        'move_time' => 0,
        'wait_time' => 0,
        'overlap_percent' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'setup_time_minutes' => 'decimal:2',
            'run_time_per_unit' => 'decimal:4',
            'labor_time' => 'decimal:2',
            'queue_time' => 'decimal:2',
            'move_time' => 'decimal:2',
            'wait_time' => 'decimal:2',
            'overlap_percent' => 'decimal:2',
        ];
    }

    public function routingHeader(): BelongsTo
    {
        return $this->belongsTo(RoutingHeader::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
