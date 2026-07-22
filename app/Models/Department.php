<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActivePlant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToActivePlant, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'organization_id', 'plant_id'];

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
}
