<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', config('barbearia.timezone')));
        config(['barbearia.asaas.webhook_token' => 'segredo']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fakeAsaas(): void
    {
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_123']),
            '*/payments/pay_123/pixQrCode' => Http::response(['payload' => '000201-copia-e-cola', 'encodedImage' => 'BASE64']),
            '*/payments' => Http::response(['id' => 'pay_123', 'invoiceUrl' => 'https://asaas.test/i/pay_123']),
        ]);
    }

    private function barber(): Barber
    {
        $barber = Barber::factory()->create();

        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            $barber->workingHours()->create(['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        }

        return $barber;
    }

    private function payload(Service $service, Barber $barber): array
    {
        return [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'starts_at' => Carbon::parse('2026-09-03 09:00', config('barbearia.timezone'))->toIso8601String(),
            'name' => 'Matheus Oliveira',
            'phone' => '(11) 98888-7777',
            'email' => 'matheus@example.com',
            'document' => '390.533.447-05',
            'billing_type' => 'PIX',
        ];
    }

    public function test_abre_cobranca_pix_junto_com_a_reserva(): void
    {
        $this->fakeAsaas();

        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60, 'price_cents' => 4500]);

        $this->postJson('/agendamentos', $this->payload($service, $barber))->assertCreated();

        $payment = Payment::firstOrFail();

        $this->assertSame('pay_123', $payment->provider_payment_id);
        $this->assertSame('PIX', $payment->billing_type);
        $this->assertSame(4500, $payment->amount_cents);
        $this->assertSame('000201-copia-e-cola', $payment->pix_payload);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('cus_123', Customer::firstOrFail()->asaas_customer_id);
        $this->assertSame('39053344705', Customer::firstOrFail()->document);
    }

    public function test_falha_no_gateway_devolve_502_e_deixa_a_reserva_expirar(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['description' => 'nope']]], 400)]);

        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60]);

        $this->postJson('/agendamentos', $this->payload($service, $barber))->assertStatus(502);

        $this->assertSame(AppointmentStatus::PendingPayment, Appointment::firstOrFail()->status);
    }

    public function test_webhook_confirmado_confirma_o_agendamento(): void
    {
        $appointment = Appointment::factory()->pending()->create();
        $appointment->payment()->create([
            'provider_payment_id' => 'pay_123',
            'billing_type' => 'PIX',
            'amount_cents' => 4500,
            'status' => PaymentStatus::Pending,
        ]);

        $this->webhook('evt_1', 'PAYMENT_CONFIRMED')->assertOk();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->refresh()->status);
        $this->assertNull($appointment->reserved_until);
        $this->assertNotNull($appointment->confirmed_at);
        $this->assertSame(PaymentStatus::Confirmed, $appointment->payment->status);
    }

    public function test_webhook_repetido_nao_processa_duas_vezes(): void
    {
        $appointment = Appointment::factory()->pending()->create();
        $appointment->payment()->create([
            'provider_payment_id' => 'pay_123',
            'billing_type' => 'PIX',
            'amount_cents' => 4500,
            'status' => PaymentStatus::Pending,
        ]);

        $this->webhook('evt_1', 'PAYMENT_CONFIRMED')->assertOk();
        $confirmedAt = $appointment->refresh()->confirmed_at;

        Carbon::setTestNow(now()->addMinutes(5));
        $this->webhook('evt_1', 'PAYMENT_CONFIRMED')->assertOk();

        $this->assertSame(1, WebhookEvent::count());
        $this->assertTrue($confirmedAt->equalTo($appointment->refresh()->confirmed_at));
    }

    public function test_webhook_de_estorno_cancela_o_agendamento(): void
    {
        $appointment = Appointment::factory()->create();
        $appointment->payment()->create([
            'provider_payment_id' => 'pay_123',
            'billing_type' => 'PIX',
            'amount_cents' => 4500,
            'status' => PaymentStatus::Confirmed,
        ]);

        $this->webhook('evt_2', 'PAYMENT_REFUNDED')->assertOk();

        $this->assertSame(AppointmentStatus::Canceled, $appointment->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $appointment->payment->status);
    }

    public function test_webhook_recusa_token_invalido(): void
    {
        $this->postJson('/webhooks/asaas', ['id' => 'evt_3', 'event' => 'PAYMENT_CONFIRMED'], ['asaas-access-token' => 'errado'])
            ->assertStatus(401);

        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_status_publico_responde_o_polling(): void
    {
        $appointment = Appointment::factory()->pending()->create();

        $this->getJson("/agendamentos/{$appointment->public_token}/status")
            ->assertOk()
            ->assertJsonPath('status', 'pending_payment');
    }

    public function test_comando_expira_reservas_vencidas(): void
    {
        $vencida = Appointment::factory()->expired()->create(['status' => AppointmentStatus::PendingPayment]);
        $viva = Appointment::factory()->pending()->create();

        $this->artisan('appointments:expire')->assertExitCode(0);

        $this->assertSame(AppointmentStatus::Expired, $vencida->refresh()->status);
        $this->assertSame(AppointmentStatus::PendingPayment, $viva->refresh()->status);
    }

    public function test_tela_de_acompanhamento_mostra_a_cobranca(): void
    {
        $appointment = Appointment::factory()->pending()->create();
        $appointment->payment()->create([
            'provider_payment_id' => 'pay_123',
            'billing_type' => 'PIX',
            'amount_cents' => 4500,
            'status' => PaymentStatus::Pending,
            'pix_payload' => 'copia-e-cola',
        ]);

        $this->get("/agendamentos/{$appointment->public_token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('agendar/acompanhamento')
                ->where('appointment.status', 'pending_payment')
                ->where('appointment.payment.pix_payload', 'copia-e-cola'));
    }

    public function test_baixa_o_ics_do_agendamento(): void
    {
        $appointment = Appointment::factory()->create();

        $response = $this->get("/agendamentos/{$appointment->public_token}/agenda.ics")->assertOk();

        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VEVENT', $response->getContent());
        $this->assertStringContainsString('DTSTART:', $response->getContent());
    }

    private function webhook(string $id, string $event)
    {
        return $this->postJson('/webhooks/asaas', [
            'id' => $id,
            'event' => $event,
            'payment' => ['id' => 'pay_123', 'value' => 45.0],
        ], ['asaas-access-token' => 'segredo']);
    }
}
