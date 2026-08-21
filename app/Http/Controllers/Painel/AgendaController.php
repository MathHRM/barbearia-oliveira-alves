<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\AgendaService;
use App\Support\PainelScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function __construct(private readonly AgendaService $agenda) {}

    public function index(Request $request): Response
    {
        $scope = PainelScope::for($request->user());

        $date = $this->date($request->query('date'));
        $view = $this->view($request->query('view'));
        $barberId = $scope->resolveBarberId($request->integer('barber_id') ?: null);

        [$from, $to] = $this->range($date, $view);
        $rows = $this->agenda->rowsBetween($from, $to, $barberId);
        $totals = $this->agenda->totals($rows, $date, $barberId, $from, $to);

        if (! $scope->canSeeRevenue()) {
            unset($totals['estimated_cents']);
            unset($totals['earned_cents']);
        }

        $day = Carbon::parse($date, config('barbearia.timezone'));

        $previous = $this->shift($day, $view, -1);
        $following = $this->shift($day, $view, 1);

        return Inertia::render('painel/agenda', [
            'date' => $date,
            'view' => $view,
            'rangeStart' => $from->toDateString(),
            'rangeEnd' => $to->toDateString(),
            'prev' => $previous->toDateString(),
            'next' => $following->toDateString(),
            'today' => now()->timezone(config('barbearia.timezone'))->toDateString(),
            'barberId' => $scope->isOwner() ? $barberId : null,
            'barbers' => $scope->barbers()->map(fn ($barber) => [
                'id' => $barber->id,
                'name' => $barber->display_name,
            ])->values(),
            'rows' => $rows->values(),
            'blocks' => $this->agenda->blocks($date, $barberId)->values(),
            'totals' => $totals,
            'services' => Service::active()->get()->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_min' => $service->duration_min,
                'price_cents' => $service->price_cents,
            ]),
            'can' => [
                'see_revenue' => $scope->canSeeRevenue(),
                'filter_barbers' => $scope->isOwner(),
            ],
        ]);
    }

    /** Data pedida na URL, ou hoje quando vier vazia/inválida. */
    private function date(?string $value): string
    {
        try {
            return Carbon::parse($value ?: 'today', config('barbearia.timezone'))->toDateString();
        } catch (\Throwable) {
            return now()->timezone(config('barbearia.timezone'))->toDateString();
        }
    }

    private function view(?string $value): string
    {
        return in_array($value, ['day', 'week', 'month'], true) ? $value : 'day';
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(string $date, string $view): array
    {
        $day = Carbon::parse($date, config('barbearia.timezone'));

        return match ($view) {
            'week' => [$day->copy()->startOfWeek(Carbon::MONDAY), $day->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$day->copy()->startOfMonth(), $day->copy()->endOfMonth()],
            default => [$day->copy()->startOfDay(), $day->copy()->endOfDay()],
        };
    }

    private function shift(Carbon $day, string $view, int $direction): Carbon
    {
        return match ($view) {
            'week' => $day->copy()->addWeeks($direction)->startOfWeek(Carbon::MONDAY),
            'month' => $day->copy()->addMonths($direction)->startOfMonth(),
            default => $day->copy()->addDays($direction),
        };
    }
}
