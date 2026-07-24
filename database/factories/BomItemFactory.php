<?php

namespace Database\Factories;

use App\Models\BomItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BomItem>
 */
class BomItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity' => fake()->randomFloat(4, 0.1, 20),
            'scrap_percentage' => 0,
            'sequence' => 10,
            'notes' => null,
        ];
    }
}
