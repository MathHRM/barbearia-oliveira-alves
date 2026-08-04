<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            TeamSeeder::class,
        ]);

        // Histórico fake só fora de produção.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
