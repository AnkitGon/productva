<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plant_id' => Plant::factory(),
            'employee_code' => 'EMP-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'job_title' => fake()->jobTitle(),
            'employment_type' => 'Full-Time',
            'status' => 'Active',
        ];
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Terminated',
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Active',
        ]);
    }
}
