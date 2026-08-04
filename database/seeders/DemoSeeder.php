<?php

namespace Database\Seeders;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Histórico fake dos últimos 90 dias para o dashboard ter o que mostrar.
 * Slots sorteados sem repetição por barbeiro/dia — a constraint EXCLUDE não perdoa.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $barbers = Barber::orderBy('sort_order')->get();
        $services = Service::active()->get();

        if ($barbers->isEmpty() || $services->isEmpty()) {
            return;
        }

        $customers = Customer::factory(40)->create();

        for ($day = 90; $day >= -14; $day--) {
            $date = Carbon::today()->subDays($day);

            if (in_array($date->dayOfWeek, [0, 1], true)) {
                continue;   // fechado
            }

            foreach ($barbers as $barber) {
                $hours = collect(range(9, 12))->merge(range(14, 18))->shuffle()
                    ->take(random_int(2, 6));

                foreach ($hours as $hour) {
                    $service = $services->random();
                    $starts = $date->copy()->setTime($hour, 0);
                    $status = $this->status($starts);

                    Appointment::create([
                        'customer_id' => $customers->random()->id,
                        'barber_id' => $barber->id,
                        'service_id' => $service->id,
                        'starts_at' => $starts,
                        'ends_at' => $starts->copy()->addMinutes($service->duration_min),
                        'status' => $status,
                        'origin' => random_int(1, 10) > 8 ? AppointmentOrigin::Manual : AppointmentOrigin::Online,
                        'price_cents' => $service->price_cents,
                        'duration_min' => $service->duration_min,
                        'confirmed_at' => $starts->copy()->subDays(2),
                        'attended_at' => $status === AppointmentStatus::Attended ? $starts : null,
                        'canceled_at' => $status === AppointmentStatus::Canceled ? $starts->copy()->subDay() : null,
                    ]);
                }
            }
        }

        $this->syncLastVisit();
    }

    private function status(Carbon $starts): AppointmentStatus
    {
        if ($starts->isFuture()) {
            return AppointmentStatus::Confirmed;
        }

        return match (true) {
            random_int(1, 100) <= 82 => AppointmentStatus::Attended,
            random_int(1, 100) <= 60 => AppointmentStatus::NoShow,
            default => AppointmentStatus::Canceled,
        };
    }

    private function syncLastVisit(): void
    {
        Customer::query()->eachById(function (Customer $customer) {
            $last = $customer->appointments()
                ->where('status', AppointmentStatus::Attended)
                ->max('starts_at');

            $customer->forceFill([
                'last_visit_at' => $last,
                'first_seen_at' => $customer->appointments()->min('starts_at') ?? $customer->first_seen_at,
            ])->save();
        });
    }
}
