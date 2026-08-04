<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => 'Corte '.fake()->unique()->word(),
            'description' => fake()->sentence(6),
            'duration_min' => fake()->randomElement([15, 30, 45, 60]),
            'price_cents' => fake()->numberBetween(2000, 12000),
            'sort_order' => 0,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
