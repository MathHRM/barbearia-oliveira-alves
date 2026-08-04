<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'provider' => 'asaas',
            'provider_payment_id' => 'pay_'.fake()->unique()->numerify('###########'),
            'billing_type' => 'PIX',
            'amount_cents' => 4500,
            'status' => PaymentStatus::Pending,
        ];
    }

    public function confirmed(): static
    {
        return $this->state([
            'status' => PaymentStatus::Confirmed,
            'paid_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state([
            'status' => PaymentStatus::Refunded,
            'paid_at' => now()->subDay(),
            'refunded_at' => now(),
        ]);
    }
}
