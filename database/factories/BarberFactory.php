<?php

namespace Database\Factories;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Barber> */
class BarberFactory extends Factory
{
    protected $model = Barber::class;

    public function definition(): array
    {
        $name = fake()->firstName('male').' '.fake()->lastName();

        return [
            'user_id' => User::factory(),
            'display_name' => $name,
            'headline' => fake()->randomElement(['Degradê e navalha', 'Clássico e barba', 'Cortes sociais']),
            'initials' => Str::upper(Str::substr($name, 0, 1).Str::substr(Str::afterLast($name, ' '), 0, 1)),
            'sort_order' => 0,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
