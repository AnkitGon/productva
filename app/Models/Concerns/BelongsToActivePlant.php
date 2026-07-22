<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToActivePlant
{
    /**
     * Limit results to the authenticated user's organization and active plant.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForActivePlant(Builder $query, User $user): Builder
    {
        return $query
            ->where($query->getModel()->getTable().'.organization_id', $user->organization_id)
            ->where($query->getModel()->getTable().'.plant_id', $user->active_plant_id);
    }
}
