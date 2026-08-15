<?php

namespace Tests\Feature\Painel;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgendaTest extends TestCase
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

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    /** Barbeiro com expediente das 09h às 18h de segunda a sábado. */
    private function barber(?User $user = null): Barber
    {
        $barber = Barber::factory()->create($user ? ['user_id' => $user->id] : []);

        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            $barber->workingHours()->create(['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        }

        return $barber;
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-09-02 '.$time, config('barbearia.timezone'));
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/painel/agenda')->assertRedirect('/login');
    }

    public function test_login_do_painel_renderiza_a_tela_de_entrada(): void
    {
        $this->get('/painel/login')->assertOk()->assertInertia(fn ($page) => $page->component('auth/login'));
    }

    public function test_agenda_lista_o_dia_com_os_numeros(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 30, 'price_cents' => 4500]);

        Appointment::factory()->for($barber)->for($service)->at($this->at('10:00'))->create();
        Appointment::factory()->for($barber)->for($service)->at($this->at('11:00'))->attended()->create();
        Appointment::factory()->for($barber)->for($service)->at($this->at('12:00'))->canceled()->create();

        $this->actingAs($this->owner())
            ->get('/painel/agenda?date=2026-09-02')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('painel/agenda')
                ->where('date', '2026-09-02')
                ->has('rows', 3)
                ->where('rows.0.starts_at', '10:00')
                ->where('totals.confirmed', 1)
                ->where('totals.attended', 1)
                ->where('totals.canceled', 1)
                ->where('totals.estimated_cents', 9000)
                ->where('can.see_revenue', true));
    }

    /** O painel mostra valor estimado, sem inferir recebimento. */
    public function test_valor_estimado_soma_atendimentos_e_agendamentos_validos(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 30, 'price_cents' => 4500]);
        $make = fn (string $time) => Appointment::factory()->for($barber)->for($service)->at($this->at($time));

        $make('08:00')->create();                  // confirmado → estimado
        $make('09:00')->create();                  // confirmado     → previsto
        $make('10:00')->noShow()->create();        // faltou         → previsto
        $make('11:00')->attended()->create();      // compareceu     → previsto e recebido

        $this->actingAs($this->owner())
            ->get('/painel/agenda?date=2026-09-02')
            ->assertInertia(fn ($page) => $page
                ->where('totals.estimated_cents', 18000));
    }

    public function test_barbeiro_ve_so_a_propria_agenda_e_sem_faturamento(): void
    {
        $user = User::factory()->barber()->create();
        $mine = $this->barber($user);
        $other = $this->barber();
        $service = Service::factory()->create(['duration_min' => 30]);

        Appointment::factory()->for($mine)->for($service)->at($this->at('10:00'))->create();
        Appointment::factory()->for($other)->for($service)->at($this->at('11:00'))->create();

        $this->actingAs($user)
            ->get('/painel/agenda?date=2026-09-02&barber_id='.$other->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.barber_id', $mine->id)
                ->where('can.see_revenue', false)
                ->missing('totals.estimated_cents'));
    }

    public function test_marca_comparecimento_e_atualiza_a_ultima_visita(): void
    {
        $barber = $this->barber();
        $appointment = Appointment::factory()->for($barber)->at($this->at('10:00'))->create();
        Carbon::setTestNow($this->at('10:30'));

        $this->actingAs($this->owner())
            ->post("/painel/agendamentos/{$appointment->id}/compareceu")
            ->assertRedirect();

        $this->assertSame(AppointmentStatus::Attended, $appointment->refresh()->status);
        $this->assertTrue($appointment->customer->refresh()->last_visit_at->equalTo($appointment->starts_at));
    }

    public function test_marca_falta(): void
    {
        $appointment = Appointment::factory()->for($this->barber())->at($this->at('10:00'))->create();
        Carbon::setTestNow($this->at('10:30'));

        $this->actingAs($this->owner())->post("/painel/agendamentos/{$appointment->id}/faltou");

        $this->assertSame(AppointmentStatus::NoShow, $appointment->refresh()->status);
    }

    public function test_nao_marca_comparecimento_antes_da_hora(): void
    {
        $appointment = Appointment::factory()->for($this->barber())->at($this->at('10:00'))->create();

        $this->actingAs($this->owner())
            ->post("/painel/agendamentos/{$appointment->id}/compareceu")
            ->assertSessionHas('error', 'Esse horário ainda não chegou.');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->refresh()->status);
    }

    public function test_agenda_esconde_os_botoes_de_presenca_no_futuro(): void
    {
        Appointment::factory()->for($this->barber())->at($this->at('10:00'))->create();

        $this->actingAs($this->owner())
            ->get('/painel/agenda?date=2026-09-02')
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.can_attend', false)
                ->where('rows.0.can_no_show', false));
    }

    public function test_falta_ja_marcada_nao_oferece_faltou_de_novo(): void
    {
        Appointment::factory()
            ->for($this->barber())
            ->at($this->at('10:00'))
            ->create(['status' => AppointmentStatus::NoShow]);

        Carbon::setTestNow($this->at('10:30'));

        $this->actingAs($this->owner())
            ->get('/painel/agenda?date=2026-09-02')
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.can_attend', true)
                ->where('rows.0.can_no_show', false));
    }

    public function test_barbeiro_nao_mexe_em_agendamento_alheio(): void
    {
        $user = User::factory()->barber()->create();
        $this->barber($user);
        $appointment = Appointment::factory()->for($this->barber())->at($this->at('10:00'))->create();

        $this->actingAs($user)->post("/painel/agendamentos/{$appointment->id}/compareceu")->assertForbidden();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->refresh()->status);
    }

    public function test_cancelamento_nao_faz_chamada_a_provedor(): void
    {
        Http::fake(['*' => Http::response(['id' => 'pay_123', 'status' => 'REFUNDED'])]);

        $appointment = Appointment::factory()->for($this->barber())->at($this->at('10:00'))->create();
        $this->actingAs($this->owner())
            ->post("/painel/agendamentos/{$appointment->id}/cancelar", ['reason' => 'Cliente avisou'])
            ->assertRedirect();

        $this->assertSame(AppointmentStatus::Canceled, $appointment->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_lancamento_de_balcao_entra_confirmado(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 30, 'price_cents' => 4500]);

        $this->actingAs($this->owner())->post('/painel/agendamentos', [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => '2026-09-02',
            'time' => '15:00',
            'name' => 'João da Esquina',
            'phone' => '(31) 98888-7777',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $appointment = Appointment::firstOrFail();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame(AppointmentOrigin::Manual, $appointment->origin);
        $this->assertSame(4500, $appointment->price_cents);
        $this->assertSame('+5531988887777', Customer::firstOrFail()->phone_e164);
        $this->assertSame('15:00', $appointment->starts_at->timezone(config('barbearia.timezone'))->format('H:i'));
    }

    public function test_balcao_recusa_horario_ja_ocupado(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 30]);
        Appointment::factory()->for($barber)->for($service)->at($this->at('15:00'))->create();

        $this->actingAs($this->owner())->post('/painel/agendamentos', [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => '2026-09-02',
            'time' => '15:15',
            'name' => 'João da Esquina',
            'phone' => '(31) 98888-7777',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('time');

        $this->assertSame(1, Appointment::count());
    }

    public function test_bloqueio_tira_o_horario_da_agenda_publica(): void
    {
        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 30]);

        $this->actingAs($this->owner())->post('/painel/bloqueios', [
            'barber_id' => $barber->id,
            'date' => '2026-09-02',
            'starts' => '14:00',
            'ends' => '15:00',
            'reason' => 'Dentista',
        ])->assertRedirect();

        $block = TimeBlock::firstOrFail();
        $this->assertSame('14:00', $block->starts_at->timezone(config('barbearia.timezone'))->format('H:i'));

        $this->getJson("/api/availability?service_id={$service->id}&barber_id={$barber->id}&date=2026-09-02")
            ->assertOk()
            ->assertJsonMissing(['label' => '14:00'])
            ->assertJsonFragment(['label' => '15:00']);

        $this->actingAs($this->owner())->delete("/painel/bloqueios/{$block->id}")->assertRedirect();
        $this->assertSame(0, TimeBlock::count());
    }

    public function test_bloqueio_por_periodo_cria_um_por_dia(): void
    {
        $barber = $this->barber();

        $this->actingAs($this->owner())->post('/painel/bloqueios', [
            'barber_id' => $barber->id,
            'date' => '2026-09-02',
            'until' => '2026-09-06',
            'starts' => '12:00',
            'ends' => '13:00',
            'reason' => 'Férias',
        ])->assertRedirect();

        $this->assertSame(5, TimeBlock::count());

        $tz = config('barbearia.timezone');
        $dias = TimeBlock::orderBy('starts_at')->get()
            ->map(fn (TimeBlock $block) => $block->starts_at->timezone($tz)->format('Y-m-d H:i'))
            ->all();

        $this->assertSame([
            '2026-09-02 12:00',
            '2026-09-03 12:00',
            '2026-09-04 12:00',
            '2026-09-05 12:00',
            '2026-09-06 12:00',
        ], $dias);
    }

    public function test_listagem_mostra_o_periodo_do_bloqueio(): void
    {
        $owner = $this->owner();
        $barber = $this->barber();

        $this->actingAs($owner)->post('/painel/bloqueios', [
            'barber_id' => $barber->id,
            'date' => '2026-09-02',
            'until' => '2026-09-06',
            'starts' => '12:00',
            'ends' => '13:00',
            'reason' => 'Férias',
        ])->assertRedirect();

        $this->actingAs($owner)
            ->get('/painel/agenda?date=2026-09-04')
            ->assertInertia(fn ($page) => $page
                ->where('blocks.0.first_day', '02/09')
                ->where('blocks.0.last_day', '06/09')
                ->where('blocks.0.days', 5)
                ->where('blocks.0.starts_at', '12:00')
                ->where('blocks.0.ends_at', '13:00'));
    }

    public function test_remover_bloqueio_de_periodo_apaga_todos_os_dias(): void
    {
        $barber = $this->barber();

        $this->actingAs($this->owner())->post('/painel/bloqueios', [
            'barber_id' => $barber->id,
            'date' => '2026-09-02',
            'until' => '2026-09-06',
            'starts' => '12:00',
            'ends' => '13:00',
        ])->assertRedirect();

        $block = TimeBlock::orderBy('starts_at')->skip(2)->firstOrFail();

        $this->actingAs($this->owner())->delete("/painel/bloqueios/{$block->id}")->assertRedirect();

        $this->assertSame(0, TimeBlock::count());
    }

    public function test_periodo_invertido_devolve_erro_em_portugues(): void
    {
        $barber = $this->barber();

        $response = $this->actingAs($this->owner())->post('/painel/bloqueios', [
            'barber_id' => $barber->id,
            'date' => '2026-09-02',
            'starts' => '15:00',
            'ends' => '14:00',
        ])->assertSessionHasErrors('ends');

        $this->assertSame(
            'O fim do bloqueio precisa ser depois do início.',
            session('errors')->first('ends'),
        );

        $response->assertRedirect();
        $this->assertSame(0, TimeBlock::count());
    }

    public function test_agenda_mostra_quem_bloqueou(): void
    {
        $owner = $this->owner();
        $barber = $this->barber();

        $this->actingAs($owner)->post('/painel/bloqueios', [
            'barber_id' => $barber->id,
            'date' => '2026-09-02',
            'starts' => '12:00',
            'ends' => '13:00',
            'reason' => 'Almoço',
        ])->assertRedirect();

        $this->actingAs($owner)
            ->get('/painel/agenda?date=2026-09-02')
            ->assertInertia(fn ($page) => $page
                ->where('blocks.0.created_by', $owner->name)
                ->where('blocks.0.reason', 'Almoço'));
    }
}
