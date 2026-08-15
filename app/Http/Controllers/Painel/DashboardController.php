<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const RANGES = ['30d' => 30, '90d' => 90, '12m' => 365];

    public function __construct(private readonly MetricsService $metrics) {}

    public function index(Request $request): Response
    {
        $range = (string) $request->query('range', '30d');
        $range = array_key_exists($range, self::RANGES) ? $range : '30d';
        $tz = config('barbearia.timezone');

        $to = now()->timezone($tz)->endOfDay();
        $from = $to->copy()->subDays(self::RANGES[$range] - 1)->startOfDay();

        return Inertia::render('painel/dashboard', [
            'range' => $range,
            'ranges' => array_keys(self::RANGES),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'kpis' => $this->metrics->kpis($from, $to),
            'weekly' => $this->metrics->estimatedByWeek(),
            'retention' => $this->metrics->retention(),
            'services' => $this->metrics->topServices($from, $to),
            'churn_days' => (int) config('barbearia.churn_days'),
        ]);
    }
}
