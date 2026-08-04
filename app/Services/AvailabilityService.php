<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Support\Slot;
use App\Support\TimeRange;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Disponibilidade calculada na hora: working_hours − time_blocks − agendamentos que seguram slot.
 * Nada de tabela de slots materializada — mexer na grade não exige regerar nada.
 */
class AvailabilityService
{
    /**
     * Horários livres para um serviço numa janela de datas.
     *
     * @param  int|null  $barberId  null = "tanto faz", devolve slots de qualquer barbeiro
     * @return Collection<int, Slot>
     */
    public function slots(Service $service, ?int $barberId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        [$from, $to] = $this->clampWindow($from, $to);

        if ($from->gte($to)) {
            return collect();
        }

        $barbers = $this->barbers($barberId);

        if ($barbers->isEmpty()) {
            return collect();
        }

        $busy = $this->busyRanges($barbers->pluck('id')->all(), $from, $to);
        $duration = $service->duration_min;
        $step = (int) config('barbearia.slot_step_min');
        $tz = config('barbearia.timezone');

        /** @var array<string, list<int>> $found */
        $found = [];

        foreach ($this->days($from, $to, $tz) as $day) {
            foreach ($barbers as $barber) {
                foreach ($barber->workingHours->where('weekday', $day->dayOfWeek) as $hours) {
                    $window = $this->clampRange(
                        new TimeRange(
                            $this->at($day, $hours->starts_at, $tz),
                            $this->at($day, $hours->ends_at, $tz),
                        ),
                        $from,
                        $to,
                    );

                    if ($window === null) {
                        continue;
                    }

                    $cursor = $this->alignToStep($window->start, $this->at($day, $hours->starts_at, $tz), $step);

                    while ($cursor->copy()->addMinutes($duration)->lte($window->end)) {
                        $candidate = new TimeRange($cursor, $cursor->copy()->addMinutes($duration));

                        if (! $this->collides($candidate, $busy[$barber->id] ?? [])) {
                            $found[$cursor->copy()->utc()->toIso8601String()][] = $barber->id;
                        }

                        $cursor = $cursor->copy()->addMinutes($step);
                    }
                }
            }
        }

        ksort($found);

        return collect($found)->map(fn (array $barberIds, string $iso) => new Slot(
            startsAt: Carbon::parse($iso),
            endsAt: Carbon::parse($iso)->addMinutes($duration),
            barberIds: array_values(array_unique($barberIds)),
        ))->values();
    }

    /**
     * Quantos horários livres por dia — alimenta o calendário do wizard.
     *
     * @return array<string, int> data Y-m-d no fuso da barbearia => nº de horários
     */
    public function countByDay(Service $service, ?int $barberId, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->slots($service, $barberId, $from, $to)
            ->groupBy(fn (Slot $slot) => $slot->startsAt->timezone(config('barbearia.timezone'))->toDateString())
            ->map->count()
            ->all();
    }

    /** Revalida na hora de reservar: entre listar e clicar, alguém pode ter pegado o horário. */
    public function isFree(Service $service, int $barberId, CarbonInterface $startsAt): bool
    {
        return $this->slots($service, $barberId, $startsAt, $startsAt->copy()->addMinutes($service->duration_min))
            ->contains(fn (Slot $slot) => $slot->startsAt->equalTo($startsAt) && $slot->hasBarber($barberId));
    }

    /**
     * Escolhe o barbeiro quando o cliente marcou "tanto faz":
     * entre os livres, o com menos agendamentos no dia; empate resolve por ordem de exibição.
     */
    public function pickBarber(Service $service, CarbonInterface $startsAt): ?int
    {
        $slot = $this->slots($service, null, $startsAt, $startsAt->copy()->addMinutes($service->duration_min))
            ->first(fn (Slot $s) => $s->startsAt->equalTo($startsAt));

        if ($slot === null) {
            return null;
        }

        $day = $startsAt->copy()->timezone(config('barbearia.timezone'));

        $load = Appointment::query()->blocking()
            ->whereIn('barber_id', $slot->barberIds)
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->selectRaw('barber_id, count(*) as total')
            ->groupBy('barber_id')
            ->pluck('total', 'barber_id');

        $order = Barber::whereIn('id', $slot->barberIds)->orderBy('sort_order')->pluck('id')->all();

        usort($order, fn (int $a, int $b) => ($load[$a] ?? 0) <=> ($load[$b] ?? 0));

        return $order[0] ?? null;
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    private function clampWindow(CarbonInterface $from, CarbonInterface $to): array
    {
        $earliest = now()->addMinutes((int) config('barbearia.min_lead_min'));
        $latest = now()->timezone(config('barbearia.timezone'))
            ->addDays((int) config('barbearia.horizon_days'))->endOfDay();

        // Sempre em UTC: o binding de query é formatado sem offset, então um
        // Carbon em -03:00 viraria três horas a menos dentro do WHERE.
        return [
            ($from->lt($earliest) ? $earliest->copy() : $from->copy())->utc(),
            ($to->gt($latest) ? $latest->copy() : $to->copy())->utc(),
        ];
    }

    /** @return Collection<int, Barber> */
    private function barbers(?int $barberId): Collection
    {
        return Barber::query()
            ->where('active', true)
            ->when($barberId, fn ($query) => $query->whereKey($barberId))
            ->with('workingHours')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Bloqueios e agendamentos ocupados, agrupados por barbeiro.
     *
     * @param  list<int>  $barberIds
     * @return array<int, list<TimeRange>>
     */
    private function busyRanges(array $barberIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $busy = [];

        $appointments = Appointment::query()->blocking()
            ->whereIn('barber_id', $barberIds)
            ->between($from, $to)
            ->get(['barber_id', 'starts_at', 'ends_at']);

        $blocks = TimeBlock::query()
            ->whereIn('barber_id', $barberIds)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['barber_id', 'starts_at', 'ends_at']);

        foreach ($appointments->concat($blocks) as $row) {
            $busy[$row->barber_id][] = new TimeRange($row->starts_at, $row->ends_at);
        }

        return $busy;
    }

    /** @param  list<TimeRange>  $busy */
    private function collides(TimeRange $candidate, array $busy): bool
    {
        foreach ($busy as $range) {
            if ($candidate->overlaps($range)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Carbon> dias (início do dia no fuso da barbearia) tocados pela janela */
    private function days(CarbonInterface $from, CarbonInterface $to, string $tz): array
    {
        $day = $from->copy()->timezone($tz)->startOfDay();
        $last = $to->copy()->timezone($tz)->startOfDay();
        $days = [];

        while ($day->lte($last)) {
            $days[] = $day->copy();
            $day = $day->addDay();
        }

        return $days;
    }

    private function at(Carbon $day, string $time, string $tz): Carbon
    {
        return Carbon::parse($day->toDateString().' '.$time, $tz);
    }

    private function clampRange(TimeRange $window, CarbonInterface $from, CarbonInterface $to): ?TimeRange
    {
        $clamped = new TimeRange(
            $window->start->lt($from) ? $from->copy() : $window->start,
            $window->end->gt($to) ? $to->copy() : $window->end,
        );

        return $clamped->isEmpty() ? null : $clamped;
    }

    /** Mantém a grade ancorada no início do expediente (09:00, 09:15…) mesmo com janela cortada. */
    private function alignToStep(CarbonInterface $cursor, Carbon $anchor, int $step): Carbon
    {
        $cursor = $cursor->copy();
        $diff = $anchor->diffInMinutes($cursor, false);

        if ($diff <= 0) {
            return $anchor->copy();
        }

        return $anchor->copy()->addMinutes((int) ceil($diff / $step) * $step);
    }
}
