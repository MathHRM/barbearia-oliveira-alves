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

class AppointmentLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', config('barbearia.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tela_de_consulta_publica_inicia_sem_agendamentos(): void
    {
        $this->get('/agendamentos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('agendar/consultar')
                ->where('appointments', [])
                ->where('searched_phone', null));
    }

    public function test_consulta_normaliza_o_whatsapp_e_ordena_os_agendamentos(): void
    {
        $customer = Customer::factory()->create(['phone_e164' => '+5511988887777']);
        $service = Service::factory()->create();
        $barber = Barber::factory()->create();

        $later = Appointment::factory()->for($customer)->for($service)->for($barber)
            ->at('2026-09-04 10:00')->create();
        $earlier = Appointment::factory()->for($customer)->for($service)->for($barber)
            ->at('2026-09-03 10:00')->create();

        $response = $this->postJson('/agendamentos/consultar', ['phone' => '(11) 98888-7777'])
            ->assertOk()
            ->assertJsonPath('phone', '(11) 98888-7777');

        $this->assertSame([$earlier->code(), $later->code()], collect($response->json('appointments'))->pluck('code')->all());
    }

    public function test_consulta_exibe_apenas_agendamentos_scheduled_do_cliente(): void
    {
        $customer = Customer::factory()->create(['phone_e164' => '+5511988887777']);
        $other = Customer::factory()->create(['phone_e164' => '+5531999999999']);

        foreach ([AppointmentStatus::Scheduled, AppointmentStatus::Canceled, AppointmentStatus::NoShow, AppointmentStatus::Attended, AppointmentStatus::Expired] as $status) {
            Appointment::factory()->for($customer)->create(['status' => $status]);
        }
        Appointment::factory()->for($other)->create();

        $this->postJson('/agendamentos/consultar', ['phone' => '+55 (11) 98888-7777'])
            ->assertOk()
            ->assertJsonCount(1, 'appointments')
            ->assertJsonPath('appointments.0.status', 'scheduled');
    }

    public function test_whatsapp_invalido_retorna_erro_contextual(): void
    {
        $this->postJson('/agendamentos/consultar', ['phone' => '99'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'Informe um WhatsApp válido com DDD.');
    }

    public function test_cancelamento_confirmado_altera_status_e_remove_da_consulta(): void
    {
        $customer = Customer::factory()->create(['phone_e164' => '+5511988887777']);
        $appointment = Appointment::factory()->for($customer)->at('2026-09-03 10:00')->create();

        $this->postJson("/agendamentos/{$appointment->public_token}/cancelar")
            ->assertOk()
            ->assertJsonPath('message', 'Agendamento cancelado.');

        $this->assertSame(AppointmentStatus::Canceled, $appointment->refresh()->status);
        $this->postJson('/agendamentos/consultar', ['phone' => '(11) 98888-7777'])
            ->assertOk()
            ->assertJsonCount(0, 'appointments');
    }

    public function test_cancelamento_fora_da_janela_e_recusado(): void
    {
        $customer = Customer::factory()->create(['phone_e164' => '+5511988887777']);
        $appointment = Appointment::factory()->for($customer)->at('2026-09-02 19:00')->create();

        $this->postJson("/agendamentos/{$appointment->public_token}/cancelar")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esse agendamento não pode mais ser cancelado pelo site.');

        $this->assertSame(AppointmentStatus::Scheduled, $appointment->refresh()->status);
    }
}
