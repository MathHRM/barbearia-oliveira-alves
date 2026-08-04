<?php

namespace Database\Factories;

use App\Models\Barber;
use App\Models\TimeBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TimeBlock> */
class TimeBlockFactory extends Factory
{
    protected $model = TimeBlock::class;

    public function definition(): array
    {
        $starts = now()->addDay()->setTime(12, 0);

        return [
            'barber_id' => Barber::factory(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'reason' => fake()->randomElement(['Almoço', 'Compromisso pessoal', 'Manutenção']),
            'created_by' => null,
        ];
    }
}
