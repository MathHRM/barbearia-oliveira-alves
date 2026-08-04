<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone_e164' => '+5511'.fake()->unique()->numerify('9########'),
            'email' => fake()->optional(0.7)->safeEmail(),
            'notes' => null,
            'asaas_customer_id' => null,
            'first_seen_at' => now()->subDays(fake()->numberBetween(0, 300)),
            'last_visit_at' => null,
        ];
    }
}
