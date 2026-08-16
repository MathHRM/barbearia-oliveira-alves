<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_agendamento_nao_faz_chamada_a_provedor_e_exige_metodo(): void
    {
        Http::fake();
        $barber = $this->barber();
        $service = Service::factory()->create();

        $this->postJson('/agendamentos', [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'name' => 'Cliente',
            'phone' => '(31) 98888-7777',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');

        $this->assertSame(0, Appointment::count());
        Http::assertNothingSent();
    }

    public function test_metodos_pix_cartao_e_dinheiro_sao_persistidos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', config('barbearia.timezone')));
        $barber = $this->barber();
        $service = Service::factory()->create();

        foreach (['pix', 'card', 'cash'] as $index => $method) {
            $this->postJson('/agendamentos', [
                'service_id' => $service->id,
                'barber_id' => $barber->id,
                'starts_at' => Carbon::parse('2026-09-03 10:00', config('barbearia.timezone'))->addDays($index)->toIso8601String(),
                'name' => 'Cliente '.$index,
                'phone' => '(31) 98888-'.(7701 + $index),
                'payment_method' => $method,
            ])->assertCreated();
        }

        $this->assertSame(['pix', 'card', 'cash'], Appointment::query()->orderBy('id')->pluck('payment_method')->all());
        $this->assertSame(3, Appointment::where('status', AppointmentStatus::Scheduled)->count());
        Carbon::setTestNow();
    }

    private function barber(): Barber
    {
        $barber = Barber::factory()->create();

        foreach (range(0, 6) as $weekday) {
            $barber->workingHours()->create([
                'weekday' => $weekday,
                'starts_at' => '09:00',
                'ends_at' => '18:00',
            ]);
        }

        return $barber;
    }
}
