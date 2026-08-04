<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $team = [
            ['Diego Alves', 'diego@barbeariaoliveiraalves.com.br', UserRole::Owner, 'Degradê e navalha', 'DA'],
            ['Marcos Lima', 'marcos@barbeariaoliveiraalves.com.br', UserRole::Barber, 'Clássico e barba', 'ML'],
            ['Rafael Souza', 'rafael@barbeariaoliveiraalves.com.br', UserRole::Barber, 'Cortes sociais', 'RS'],
        ];

        foreach ($team as $i => [$name, $email, $role, $headline, $initials]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'role' => $role,
                    'active' => true,
                    'password' => Hash::make('barbearia'),
                    'email_verified_at' => now(),
                ],
            );

            $barber = Barber::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $name,
                    'headline' => $headline,
                    'initials' => $initials,
                    'sort_order' => $i,
                    'active' => true,
                ],
            );

            $this->workingHours($barber);
        }
    }

    /** Terça a sábado, 09–13 e 14–19. Domingo e segunda fechados. */
    private function workingHours(Barber $barber): void
    {
        $barber->workingHours()->delete();

        foreach ([2, 3, 4, 5, 6] as $weekday) {
            foreach ([['09:00', '13:00'], ['14:00', '19:00']] as [$starts, $ends]) {
                $barber->workingHours()->create([
                    'weekday' => $weekday,
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                ]);
            }
        }
    }
}
