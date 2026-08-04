<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReserveAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // quarta 02/09/2026 08:00 na barbearia; os testes reservam na quinta 03/09
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', config('barbearia.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function barber(int $sortOrder = 0): Barber
    {
        $barber = Barber::factory()->create(['sort_order' => $sortOrder]);

        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            $barber->workingHours()->create(['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        }

        return $barber;
    }

    private function payload(Service $service, ?int $barberId, string $startsAt = '2026-09-03 09:00'): array
    {
        return [
            'service_id' => $service->id,
            'barber_id' => $barberId,
            'starts_at' => Carbon::parse($startsAt, config('barbearia.timezone'))->toIso8601String(),
            'name' => 'Matheus Oliveira',
            'phone' => '(11) 98888-7777',
            'email' => 'matheus@example.com',
            'note' => 'Costeleta baixa',
        ];
    }

    public function test_cria_reserva_pendente_com_ttl_e_preco_congelado(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60, 'price_cents' => 4500]);

        $response = $this->postJson('/agendamentos', $this->payload($service, $barber->id))->assertCreated();

        $appointment = Appointment::firstOrFail();

        $this->assertSame($appointment->public_token, $response->json('token'));
        $this->assertSame(AppointmentStatus::PendingPayment, $appointment->status);
        $this->assertSame(4500, $appointment->price_cents);
        $this->assertSame(60, $appointment->duration_min);
        $this->assertSame('Costeleta baixa', $appointment->customer_note);
        $this->assertTrue($appointment->reserved_until->equalTo(now()->addMinutes(config('barbearia.reservation_ttl_min'))));
        $this->assertSame('2026-09-03 12:00', $appointment->starts_at->format('Y-m-d H:i')); // 09:00 em -03:00
    }

    public function test_reaproveita_o_cliente_pelo_telefone(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60]);

        Customer::factory()->create(['phone_e164' => '+5511988887777', 'name' => 'Nome antigo']);

        $this->postJson('/agendamentos', $this->payload($service, $barber->id))->assertCreated();

        $this->assertSame(1, Customer::count());
        $this->assertSame('Matheus Oliveira', Customer::first()->name);
    }

    public function test_recusa_horario_ja_ocupado_com_409(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60]);

        Appointment::factory()->for($barber)
            ->at(Carbon::parse('2026-09-03 09:00', config('barbearia.timezone')), 60)
            ->create();

        $this->postJson('/agendamentos', $this->payload($service, $barber->id))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Esse horário acabou de ser ocupado. Escolha outro.');
    }

    public function test_recusa_horario_fora_do_expediente(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60]);

        $this->postJson('/agendamentos', $this->payload($service, $barber->id, '2026-09-03 20:00'))
            ->assertStatus(409);
    }

    public function test_tanto_faz_escolhe_um_barbeiro_livre(): void
    {
        $ocupado = $this->barber(sortOrder: 1);
        $livre = $this->barber(sortOrder: 2);
        $service = Service::factory()->create(['duration_min' => 60]);

        Appointment::factory()->for($ocupado)
            ->at(Carbon::parse('2026-09-03 09:00', config('barbearia.timezone')), 60)
            ->create();

        $this->postJson('/agendamentos', $this->payload($service, null))->assertCreated();

        $this->assertSame($livre->id, Appointment::latest('id')->first()->barber_id);
    }

    public function test_valida_telefone(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60]);

        $this->postJson('/agendamentos', [...$this->payload($service, $barber->id), 'phone' => '99'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }
}
