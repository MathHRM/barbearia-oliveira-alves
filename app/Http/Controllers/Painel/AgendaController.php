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
        $barberId = $scope->resolveBarberId($request->integer('barber_id') ?: null);

        $rows = $this->agenda->rows($date, $barberId);
        $totals = $this->agenda->totals($rows, $date, $barberId);

        if (! $scope->canSeeRevenue()) {
            unset($totals['estimated_cents']);
        }

        $day = Carbon::parse($date, config('barbearia.timezone'));

        return Inertia::render('painel/agenda', [
            'date' => $date,
            'prev' => $day->copy()->subDay()->toDateString(),
            'next' => $day->copy()->addDay()->toDateString(),
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
}
