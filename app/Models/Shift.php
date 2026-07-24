<?php

namespace App\Models;

use App\Models\Concerns\AutoGeneratesCode;
use App\Models\Concerns\BelongsToActivePlant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use AutoGeneratesCode, BelongsToActivePlant, HasFactory, SoftDeletes;

    public const COLORS = [
        'blue',
        'orange',
        'purple',
        'green',
        'slate',
    ];

    protected $fillable = [
        'organization_id',
        'plant_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'grace_in_minutes',
        'grace_out_minutes',
        'overnight',
        'working_minutes',
        'status',
        'color',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'break_minutes' => 0,
        'grace_in_minutes' => 0,
        'grace_out_minutes' => 0,
        'overnight' => false,
        'working_minutes' => 0,
        'status' => 'Active',
        'color' => 'blue',
    ];

    protected $appends = ['hours_label', 'is_currently_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overnight' => 'boolean',
            'break_minutes' => 'integer',
            'grace_in_minutes' => 'integer',
            'grace_out_minutes' => 'integer',
            'working_minutes' => 'integer',
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

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
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
     * Gross duration in minutes from start to end (handles overnight).
     */
    public static function durationMinutes(string $startTime, string $endTime, bool $overnight): int
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        if ($overnight || $end->lessThanOrEqualTo($start)) {
            $end = $end->copy()->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    public static function calculateWorkingMinutes(
        string $startTime,
        string $endTime,
        bool $overnight,
        int $breakMinutes = 0,
    ): int {
        return max(0, self::durationMinutes($startTime, $endTime, $overnight) - max(0, $breakMinutes));
    }

    public function getHoursLabelAttribute(): string
    {
        $minutes = (int) ($this->attributes['working_minutes'] ?? $this->working_minutes ?? 0);
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($remainder === 0) {
            return $hours.'h';
        }

        return sprintf('%dh %02dm', $hours, $remainder);
    }

    /**
     * Whether this shift window is active right now (local app timezone).
     */
    public function getIsCurrentlyActiveAttribute(): bool
    {
        if (($this->attributes['status'] ?? $this->status) !== 'Active') {
            return false;
        }

        $startTime = $this->normalizeTimeString($this->attributes['start_time'] ?? $this->start_time ?? null);
        $endTime = $this->normalizeTimeString($this->attributes['end_time'] ?? $this->end_time ?? null);

        if ($startTime === null || $endTime === null) {
            return false;
        }

        $now = now();
        $start = $now->copy()->setTimeFromTimeString($startTime);
        $end = $now->copy()->setTimeFromTimeString($endTime);

        if ($this->overnight) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
    }

    private function normalizeTimeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $value = (string) $value;

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value) !== 1) {
            return null;
        }

        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
