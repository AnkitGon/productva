<?php

namespace Database\Factories;

use App\Models\RoutingOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutingOperation>
 */
class RoutingOperationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sequence' => 10,
            'setup_time_minutes' => 5,
            'run_time_per_unit' => 2,
            'labor_time' => 0,
            'queue_time' => 0,
            'move_time' => 0,
            'wait_time' => 0,
            'overlap_percent' => 0,
            'notes' => null,
        ];
    }
}
