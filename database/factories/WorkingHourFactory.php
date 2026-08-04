<?php

namespace Database\Factories;

use App\Models\Barber;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkingHour> */
class WorkingHourFactory extends Factory
{
    protected $model = WorkingHour::class;

    public function definition(): array
    {
        return [
            'barber_id' => Barber::factory(),
            'weekday' => fake()->numberBetween(1, 6),
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ];
    }
}
