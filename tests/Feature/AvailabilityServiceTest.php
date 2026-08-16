<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Services\AvailabilityService;
use App\Support\Slot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Quarta 02/09/2026 08:00 na barbearia; os testes usam quinta 03/09 como dia base. */
    private function freezeNow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', config('barbearia.timezone')));
    }

    private function local(string $datetime): Carbon
    {
        return Carbon::parse($datetime, config('barbearia.timezone'));
    }

    /** @param  list<array{0: string, 1: string}>  $hours */
    private function barberWithHours(
        array $hours = [['09:00', '13:00'], ['14:00', '18:00']],
        array $weekdays = [1, 2, 3, 4, 5, 6],
    ): Barber {
        $barber = Barber::factory()->create();

        foreach ($weekdays as $weekday) {
            foreach ($hours as [$starts, $ends]) {
                $barber->workingHours()->create([
                    'weekday' => $weekday,
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                ]);
            }
        }

        return $barber;
    }

    private function slotsFor(Service $service, ?int $barberId, string $day): Collection
    {
        $from = $this->local($day)->startOfDay();

        return app(AvailabilityService::class)->slots($service, $barberId, $from, $from->copy()->endOfDay());
    }

    /** @return list<string> */
    private function times(Collection $slots): array
    {
        return $slots->map(fn (Slot $slot) => $slot->startsAt->timezone(config('barbearia.timezone'))->format('H:i'))->all();
    }

    public function test_gera_slots_no_passo_configurado_dentro_do_expediente(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '10:00']]);
        $service = Service::factory()->create(['duration_min' => 30]);

        // passo de 15 min, serviço de 30: o último que cabe começa 09:30
        $this->assertSame(
            ['09:00', '09:15', '09:30'],
            $this->times($this->slotsFor($service, $barber->id, '2026-09-03')),
        );
    }

    public function test_nao_oferece_slot_que_ultrapassa_o_fim_do_expediente(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '10:00']]);
        $service = Service::factory()->create(['duration_min' => 60]);

        $this->assertSame(['09:00'], $this->times($this->slotsFor($service, $barber->id, '2026-09-03')));
    }

    public function test_so_abre_slots_no_weekday_configurado(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '11:00']], weekdays: [5]);
        $service = Service::factory()->create(['duration_min' => 30]);

        $this->assertCount(0, $this->slotsFor($service, $barber->id, '2026-09-03'));   // quinta
        $this->assertNotCount(0, $this->slotsFor($service, $barber->id, '2026-09-04')); // sexta
    }

    public function test_remove_horarios_ocupados_por_agendamento_que_segura_slot(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '11:00']]);
        $service = Service::factory()->create(['duration_min' => 60]);

        Appointment::factory()->for($barber)->at($this->local('2026-09-03 09:30'), 30)->create();

        // 09:00-10:00 colide com a marcação; sobra 10:00
        $this->assertSame(['10:00'], $this->times($this->slotsFor($service, $barber->id, '2026-09-03')));
    }

    public function test_ignora_agendamento_cancelado_ao_calcular_ocupacao(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '10:00']]);
        $service = Service::factory()->create(['duration_min' => 60]);

        Appointment::factory()->for($barber)->canceled()->at($this->local('2026-09-03 09:00'), 60)->create();

        $this->assertSame(['09:00'], $this->times($this->slotsFor($service, $barber->id, '2026-09-03')));
    }

    public function test_trata_agendamento_agendado_como_ocupado(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '10:00']]);
        $service = Service::factory()->create(['duration_min' => 60]);

        Appointment::factory()->for($barber)->at($this->local('2026-09-03 09:00'), 60)->create();

        $this->assertCount(0, $this->slotsFor($service, $barber->id, '2026-09-03'));
    }

    public function test_remove_horarios_cobertos_por_bloqueio(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '11:00']]);
        $service = Service::factory()->create(['duration_min' => 60]);

        TimeBlock::factory()->for($barber)->create([
            'starts_at' => $this->local('2026-09-03 09:00'),
            'ends_at' => $this->local('2026-09-03 10:00'),
        ]);

        $this->assertSame(['10:00'], $this->times($this->slotsFor($service, $barber->id, '2026-09-03')));
    }

    public function test_nao_oferece_horario_dentro_do_lead_time_minimo(): void
    {
        // agora = quinta 09:10; lead de 60 min corta tudo antes de 10:10
        Carbon::setTestNow($this->local('2026-09-03 09:10'));

        $barber = $this->barberWithHours([['09:00', '12:00']]);
        $service = Service::factory()->create(['duration_min' => 30]);

        $this->assertSame('10:15', $this->times($this->slotsFor($service, $barber->id, '2026-09-03'))[0]);
    }

    public function test_nao_passa_do_horizonte_de_agendamento(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '12:00']]);
        $service = Service::factory()->create(['duration_min' => 30]);

        $beyond = now()->addDays((int) config('barbearia.horizon_days') + 2);

        $this->assertCount(0, $this->slotsFor($service, $barber->id, $beyond->toDateString()));
    }

    public function test_une_barbeiros_quando_o_cliente_escolhe_tanto_faz(): void
    {
        $this->freezeNow();

        $a = $this->barberWithHours([['09:00', '10:00']]);
        $b = $this->barberWithHours([['09:00', '10:00']]);
        $service = Service::factory()->create(['duration_min' => 60]);

        Appointment::factory()->for($a)->at($this->local('2026-09-03 09:00'), 60)->create();

        $slots = $this->slotsFor($service, null, '2026-09-03');

        $this->assertCount(1, $slots);
        $this->assertSame([$b->id], $slots->first()->barberIds);
    }

    public function test_escolhe_o_barbeiro_menos_ocupado_no_dia(): void
    {
        $this->freezeNow();

        $a = $this->barberWithHours([['09:00', '12:00']]);
        $b = $this->barberWithHours([['09:00', '12:00']]);
        $service = Service::factory()->create(['duration_min' => 30]);

        Appointment::factory()->for($a)->at($this->local('2026-09-03 10:00'), 30)->create();
        Appointment::factory()->for($a)->at($this->local('2026-09-03 11:00'), 30)->create();

        $picked = app(AvailabilityService::class)->pickBarber($service, $this->local('2026-09-03 09:00'));

        $this->assertSame($b->id, $picked);
    }

    public function test_is_free_derruba_horario_ja_tomado(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '10:00']]);
        $service = Service::factory()->create(['duration_min' => 60]);
        $starts = $this->local('2026-09-03 09:00');

        $this->assertTrue(app(AvailabilityService::class)->isFree($service, $barber->id, $starts));

        Appointment::factory()->for($barber)->at($starts, 60)->create();

        $this->assertFalse(app(AvailabilityService::class)->isFree($service, $barber->id, $starts));
    }

    public function test_conta_horarios_livres_por_dia_para_o_calendario(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '10:00']], weekdays: [4]);
        $service = Service::factory()->create(['duration_min' => 60]);

        $counts = app(AvailabilityService::class)->countByDay(
            $service,
            $barber->id,
            $this->local('2026-09-03')->startOfDay(),
            $this->local('2026-09-10')->endOfDay(),
        );

        $this->assertSame(['2026-09-03' => 1, '2026-09-10' => 1], $counts);
    }

    public function test_ignora_barbeiro_inativo(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '10:00']]);
        $barber->update(['active' => false]);
        $service = Service::factory()->create(['duration_min' => 60]);

        $this->assertCount(0, $this->slotsFor($service, null, '2026-09-03'));
    }

    public function test_banco_recusa_dois_agendamentos_no_mesmo_slot(): void
    {
        $this->freezeNow();

        $barber = $this->barberWithHours([['09:00', '10:00']]);
        $starts = $this->local('2026-09-03 09:00');

        Appointment::factory()->for($barber)->at($starts, 60)->create();

        // a constraint EXCLUDE é a última linha de defesa contra corrida entre duas reservas
        $this->expectException(QueryException::class);

        Appointment::factory()->for($barber)->at($starts, 60)->create();
    }
}
