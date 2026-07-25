<?php

namespace App\Models;

use App\Models\Concerns\AutoGeneratesCode;
use App\Models\Concerns\BelongsToActivePlant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use AutoGeneratesCode, BelongsToActivePlant, HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'display_name',
        'gender',
        'date_of_birth',
        'photo_path',
        'organization_id',
        'plant_id',
        'department_id',
        'role_id',
        'shift_id',
        'job_title',
        'manager_id',
        'email',
        'phone',
        'mobile',
        'address',
        'employment_type',
        'hire_date',
        'status',
        'user_id',
    ];

    protected $appends = ['name', 'photo_url', 'years_of_service'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
        ];
    }

    public const GENDERS = [
        'Male',
        'Female',
        'Other',
        'Prefer not to say',
    ];

    /**
     * Employees available for assignment pickers (managers, supervisors, etc.).
     */
    public function scopeAssignable($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Whether this employee profile can log in (linked user + active status).
     */
    public function canAuthenticate(): bool
    {
        return $this->status === 'Active';
    }

    /**
     * Production / attendance / quality history blocks hard deletion.
     * Soft delete (archive) remains allowed.
     */
    public function hasOperationalHistory(): bool
    {
        // Modules not yet shipped; keep the guard ready for future relations.
        return false;
    }

    /**
     * Get the employee's full name.
     */
    public function getNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Completed years of service from hire date.
     */
    public function getYearsOfServiceAttribute(): ?int
    {
        if (! $this->hire_date) {
            return null;
        }

        return (int) $this->hire_date->diffInYears(now());
    }

    /**
     * Public URL for the employee photo.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        // Root-relative so images work regardless of APP_URL / local domain.
        return '/storage/'.ltrim($this->photo_path, '/');
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function createsCycle(int $candidateManagerId): bool
    {
        if ($this->id === $candidateManagerId) {
            return true;
        }

        $manager = self::find($candidateManagerId);
        $guard = 0;
        while ($manager && $guard < 100) {
            if ($manager->id === $this->id) {
                return true;
            }
            $manager = $manager->manager;
            $guard++;
        }

        return false;
    }
}
