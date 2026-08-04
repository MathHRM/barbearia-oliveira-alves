<?php

namespace Database\Factories;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $starts = now()->addDay()->setTime(10, 0);
        $duration = 30;

        return [
            'customer_id' => Customer::factory(),
            'barber_id' => Barber::factory(),
            'service_id' => Service::factory(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addMinutes($duration),
            'status' => AppointmentStatus::Confirmed,
            'origin' => AppointmentOrigin::Online,
            'price_cents' => 4500,
            'duration_min' => $duration,
            'confirmed_at' => now(),
        ];
    }

    /** Coloca o agendamento num horário específico, ajustando o fim pela duração. */
    public function at(\DateTimeInterface|string $starts, int $durationMin = 30): static
    {
        $starts = Carbon::parse($starts);

        return $this->state([
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addMinutes($durationMin),
            'duration_min' => $durationMin,
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'status' => AppointmentStatus::PendingPayment,
            'confirmed_at' => null,
            'reserved_until' => now()->addMinutes(config('barbearia.reservation_ttl_min')),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => AppointmentStatus::Expired,
            'confirmed_at' => null,
            'reserved_until' => now()->subMinute(),
        ]);
    }

    public function attended(): static
    {
        return $this->state([
            'status' => AppointmentStatus::Attended,
            'attended_at' => now(),
        ]);
    }

    public function noShow(): static
    {
        return $this->state(['status' => AppointmentStatus::NoShow]);
    }

    public function canceled(): static
    {
        return $this->state([
            'status' => AppointmentStatus::Canceled,
            'canceled_at' => now(),
        ]);
    }
}
