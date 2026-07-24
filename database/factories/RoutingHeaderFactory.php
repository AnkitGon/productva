<?php

namespace Database\Factories;

use App\Models\RoutingHeader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutingHeader>
 */
class RoutingHeaderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => '1.0',
            'is_default' => true,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'Draft',
            'notes' => null,
        ];
    }
}
