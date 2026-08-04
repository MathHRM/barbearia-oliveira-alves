<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['Corte masculino', 'Máquina, tesoura e finalização.', 30, 4500],
            ['Barba terapia', 'Toalha quente, navalha e hidratação.', 25, 3500],
            ['Corte + barba', 'O combo completo, sem pressa.', 55, 7000],
            ['Pezinho', 'Acabamento rápido entre cortes.', 15, 2000],
            ['Corte infantil', 'Até 12 anos, com paciência inclusa.', 30, 4000],
        ];

        foreach ($services as $i => [$name, $description, $duration, $price]) {
            Service::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'duration_min' => $duration,
                    'price_cents' => $price,
                    'sort_order' => $i,
                    'active' => true,
                ],
            );
        }
    }
}
