<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Monta a agenda de um dia: linhas, bloqueios e os números do cabeçalho. */
class AgendaService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /** @return array{0: Carbon, 1: Carbon} início e fim do dia, no fuso da barbearia */
    public function dayWindow(string $date): array
    {
        $day = Carbon::parse($date, config('barbearia.timezone'));

        return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rows(string $date, ?int $barberId): Collection
    {
        [$from, $to] = $this->dayWindow($date);

        return Appointment::query()
            ->with(['customer', 'barber', 'service'])
            ->whereBetween('starts_at', [$from, $to])
            ->when($barberId, fn ($query) => $query->where('barber_id', $barberId))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => $this->present($appointment));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function blocks(string $date, ?int $barberId): Collection
    {
        [$from, $to] = $this->dayWindow($date);

        $blocks = TimeBlock::query()
            ->with(['barber', 'creator'])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($barberId, fn ($query) => $query->where('barber_id', $barberId))
            ->orderBy('starts_at')
            ->get();

        $periods = $this->periodSpans($blocks);

        return $blocks->map(function (TimeBlock $block) use ($periods) {
            $span = $periods[$block->period_id] ?? null;
            // agregado volta como string crua em UTC — o cast do model não passa por aqui
            $first = $this->local($span ? Carbon::parse($span->first_day, 'UTC') : $block->starts_at);
            $last = $this->local($span ? Carbon::parse($span->last_day, 'UTC') : $block->starts_at);

            return [
                'id' => $block->id,
                'barber' => $block->barber->display_name,
                'starts_at' => $this->local($block->starts_at)->format('H:i'),
                'ends_at' => $this->local($block->ends_at)->format('H:i'),
                'first_day' => $first->format('d/m'),
                'last_day' => $last->format('d/m'),
                'days' => $span ? (int) $span->days : 1,
                'reason' => $block->reason,
                'created_by' => $block->creator?->name,
                'created_at' => $this->local($block->created_at)->format('d/m H:i'),
            ];
        });
    }

    /**
     * Férias viram uma linha por dia — o period_id devolve o intervalo original.
     *
     * @param  Collection<int, TimeBlock>  $blocks
     * @return Collection<string, object>
     */
    private function periodSpans(Collection $blocks): Collection
    {
        $ids = $blocks->pluck('period_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return TimeBlock::query()
            ->selectRaw('period_id, min(starts_at) as first_day, max(starts_at) as last_day, count(*) as days')
            ->whereIn('period_id', $ids)
            ->groupBy('period_id')
            ->get()
            ->keyBy('period_id');
    }

    /**
     * "Hoje em números". O valor referencial só entra na tela do dono — o scope decide.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function totals(Collection $rows, string $date, ?int $barberId): array
    {
        $by = fn (string $status) => $rows->where('status', $status);

        return [
            'total' => $rows->count(),
            'scheduled' => $by(AppointmentStatus::Scheduled->value)->count(),
            'attended' => $by(AppointmentStatus::Attended->value)->count(),
            'canceled' => $by(AppointmentStatus::Canceled->value)->count()
                + $by(AppointmentStatus::NoShow->value)->count(),
            'estimated_cents' => (int) $rows->whereIn('status', [
                AppointmentStatus::Scheduled->value,
                AppointmentStatus::Attended->value,
                AppointmentStatus::NoShow->value,
            ])->sum('price_cents'),
            'free_slots' => $this->freeSlots($date, $barberId),
        ];
    }

    /** Quantos horários ainda dá para vender no dia, medidos pelo serviço mais curto. */
    private function freeSlots(string $date, ?int $barberId): int
    {
        $service = Service::active()->orderBy('duration_min')->first();

        if ($service === null) {
            return 0;
        }

        [$from, $to] = $this->dayWindow($date);

        return $this->availability->slots($service, $barberId, $from, $to)->count();
    }

    /** @return array<string, mixed> */
    private function present(Appointment $appointment): array
    {
        $customer = $appointment->customer;
        return [
            'id' => $appointment->id,
            'code' => $appointment->code(),
            'starts_at' => $this->local($appointment->starts_at)->format('H:i'),
            'ends_at' => $this->local($appointment->ends_at)->format('H:i'),
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'tone' => $appointment->status->tone(),
            'origin' => $appointment->origin->label(),
            'service' => $appointment->service->name,
            'duration_min' => $appointment->duration_min,
            'price_cents' => $appointment->price_cents,
            'barber' => $appointment->barber->display_name,
            'barber_id' => $appointment->barber_id,
            'note' => $appointment->customer_note,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => Phone::format($customer->phone_e164),
                'visits' => $customer->appointments()->where('status', AppointmentStatus::Attended)->count(),
            ],
            'payment_method' => $appointment->payment_method,
            // presença/falta só depois do horário começar — ninguém compareceu a algo que não aconteceu
            'can_attend' => $appointment->starts_at->isPast()
                && in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::NoShow], true),
            'can_no_show' => $appointment->starts_at->isPast()
                && $appointment->status === AppointmentStatus::Scheduled,
            'can_cancel' => in_array($appointment->status, AppointmentStatus::blocking(), true),
        ];
    }

    private function local(Carbon $moment): Carbon
    {
        return $moment->copy()->timezone(config('barbearia.timezone'));
    }
}
