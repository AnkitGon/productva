<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\WarehouseType;
use Illuminate\Database\Seeder;

class WarehouseTypeSeeder extends Seeder
{
    /**
     * Seed default warehouse types for every organization.
     */
    public function run(): void
    {
        Organization::query()->pluck('id')->each(function (int $organizationId): void {
            WarehouseType::ensureDefaultsFor($organizationId);
        });
    }
}
