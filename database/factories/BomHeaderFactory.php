<?php

namespace Database\Factories;

use App\Models\BomHeader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BomHeader>
 */
class BomHeaderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => '1.0',
            'is_default' => true,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'Active',
            'notes' => null,
        ];
    }
}
