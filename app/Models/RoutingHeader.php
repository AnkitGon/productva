<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActivePlant;
use Database\Factories\RoutingHeaderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutingHeader extends Model
{
    /** @use HasFactory<RoutingHeaderFactory> */
    use BelongsToActivePlant, HasFactory, SoftDeletes;

    public const STATUSES = [
        'Draft',
        'Released',
        'Obsolete',
    ];

    protected $fillable = [
        'organization_id',
        'plant_id',
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
     * @var list<string>
     */
    protected $appends = [
        'is_editable',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class)->orderBy('sequence')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->status === 'Draft';
    }

    /**
     * @return array{setup: float, run: float, total: float}
     */
    public function estimatedTimes(): array
    {
        $this->loadMissing('operations');

        $setup = round((float) $this->operations->sum('setup_time_minutes'), 2);
        $run = round((float) $this->operations->sum('run_time_per_unit'), 4);

        return [
            'setup' => $setup,
            'run' => $run,
            'total' => round($setup + $run, 4),
        ];
    }
}
