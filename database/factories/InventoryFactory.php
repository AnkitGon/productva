<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plant_id' => Plant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'warehouse_location_id' => WarehouseLocation::factory(),
            'product_id' => Product::factory(),
            'lot_number' => '',
            'serial_number' => '',
            'quantity_on_hand' => fake()->randomFloat(2, 1, 1000),
            'quantity_reserved' => 0,
        ];
    }
}
