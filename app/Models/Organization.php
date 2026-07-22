<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name'];

    /**
     * Get the plants owned by the organization.
     */
    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class);
    }

    /**
     * Get the users belonging to the organization.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
