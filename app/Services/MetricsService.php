<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Números referenciais do dashboard, baseados nos atendimentos realizados.
 */
class MetricsService
{
    /**
     * KPIs do período, cada um com o delta contra o período anterior de mesmo tamanho.
     *
     * @return array<string, array{value: int|float, delta: int|null}>
     */
    public function kpis(Carbon $from, Carbon $to): array
    {
        $length = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($length - 1)->startOfDay();

        $current = $this->period($from, $to);
        $previous = $this->period($previousFrom, $previousTo);

        $churn = $this->churnAt(now());
        $churnBefore = $this->churnAt(now()->subDays($length));

        return [
            'estimated_cents' => ['value' => $current['estimated'], 'delta' => $this->delta($current['estimated'], $previous['estimated'])],
            'appointments' => ['value' => $current['appointments'], 'delta' => $this->delta($current['appointments'], $previous['appointments'])],
            'average_cents' => ['value' => $current['average'], 'delta' => $this->delta($current['average'], $previous['average'])],
            'churn_rate' => ['value' => $churn, 'delta' => $this->delta($churn, $churnBefore)],
            'no_show_rate' => ['value' => $current['no_show_rate'], 'delta' => $this->delta($current['no_show_rate'], $previous['no_show_rate'])],
        ];
    }

    /** @return array<string, int|float> */
    private function period(Carbon $from, Carbon $to): array
    {
        $rows = Appointment::query()
            ->whereBetween('starts_at', [$from, $to])
            ->selectRaw('status, count(*) as total, coalesce(sum(price_cents), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (AppointmentStatus $status) => (int) ($rows[$status->value]->total ?? 0);

        $attended = $count(AppointmentStatus::Attended);
        $noShow = $count(AppointmentStatus::NoShow);
        $estimated = (int) ($rows[AppointmentStatus::Attended->value]->amount ?? 0);

        return [
            'estimated' => $estimated,
            // agendamentos = tudo que chegou a valer horário no período
            'appointments' => $attended + $noShow + $count(AppointmentStatus::Confirmed),
            'average' => $attended === 0 ? 0 : (int) round($estimated / $attended),
            'no_show_rate' => ($attended + $noShow) === 0 ? 0.0 : round($noShow * 100 / ($attended + $noShow), 1),
        ];
    }

    /**
     * Churn: entre quem já tem histórico, quantos não voltam há mais de `churn_days`.
     * Medido numa data de referência para dar comparação com o período anterior.
     */
    public function churnAt(Carbon $reference): float
    {
        $days = (int) config('barbearia.churn_days');

        $withHistory = Customer::whereNotNull('last_visit_at')
            ->where('last_visit_at', '<=', $reference)
            ->count();

        if ($withHistory === 0) {
            return 0.0;
        }

        $lost = Customer::whereNotNull('last_visit_at')
            ->where('last_visit_at', '<=', $reference)
            ->where('last_visit_at', '<', $reference->copy()->subDays($days))
            ->count();

        return round($lost * 100 / $withHistory, 1);
    }

    /**
     * Valor estimado por semana (segunda a domingo), da mais antiga para a mais nova.
     *
     * @return list<array{label: string, estimated_cents: int, appointments: int}>
     */
    public function estimatedByWeek(int $weeks = 12): array
    {
        $tz = config('barbearia.timezone');
        // segunda-feira, igual ao date_trunc('week') do Postgres
        $start = now()->timezone($tz)->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);

        // a semana é fechada no fuso da barbearia, não em UTC — senão segunda de manhã cai na semana anterior
        $rows = Appointment::query()
            ->where('status', AppointmentStatus::Attended)
            ->where('starts_at', '>=', $start->copy()->utc())
            ->selectRaw("to_char(date_trunc('week', starts_at at time zone ?), 'YYYY-MM-DD') as week", [$tz])
            ->selectRaw('sum(price_cents) as amount, count(*) as total')
            ->groupBy('week')
            ->get()
            ->keyBy('week');

        $weekly = [];

        for ($i = 0; $i < $weeks; $i++) {
            $week = $start->copy()->addWeeks($i);
            $row = $rows[$week->toDateString()] ?? null;

            $weekly[] = [
                'label' => $week->format('d/m'),
                'estimated_cents' => (int) ($row->amount ?? 0),
                'appointments' => (int) ($row->total ?? 0),
            ];
        }

        return $weekly;
    }

    /**
     * Retenção: de quem já veio, quantos voltaram dentro de cada janela.
     *
     * @return array<string, int>
     */
    public function retention(): array
    {
        $now = now();
        $churnDays = (int) config('barbearia.churn_days');

        $base = fn () => Customer::whereNotNull('last_visit_at');

        return [
            'recent_30' => (clone $base())->where('last_visit_at', '>=', $now->copy()->subDays(30))->count(),
            'recent_60' => (clone $base())->whereBetween('last_visit_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count(),
            'lost' => (clone $base())->where('last_visit_at', '<', $now->copy()->subDays($churnDays))->count(),
            'total' => (clone $base())->count(),
        ];
    }

    /** Serviços mais realizados, com valor estimado no período. */
    public function topServices(Carbon $from, Carbon $to, int $limit = 5): array
    {
        return Appointment::query()
            ->where('appointments.status', AppointmentStatus::Attended)
            ->whereBetween('starts_at', [$from, $to])
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->selectRaw('services.name, count(*) as total, sum(appointments.price_cents) as amount')
            ->groupBy('services.name')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'appointments' => (int) $row->total,
                'estimated_cents' => (int) $row->amount,
            ])
            ->all();
    }

    /** Variação percentual; null quando não há base de comparação. */
    private function delta(int|float $current, int|float $previous): ?int
    {
        if ($previous == 0) {
            return null;
        }

        return (int) round(($current - $previous) * 100 / $previous);
    }
}
