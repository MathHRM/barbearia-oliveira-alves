<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Support\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Alimenta o passo 03 do wizard: quantos horários cada dia tem e, se um dia
     * foi escolhido, a lista de horários daquele dia.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $service = Service::active()->findOrFail($data['service_id']);
        $barberId = $data['barber_id'] ?? null;
        $tz = config('barbearia.timezone');

        $from = now()->timezone($tz)->startOfDay();
        $to = $from->copy()->addDays((int) config('barbearia.horizon_days'))->endOfDay();

        $counts = $this->availability->countByDay($service, $barberId, $from, $to);

        $slots = [];

        if (isset($data['date'])) {
            $day = Carbon::parse($data['date'], $tz)->startOfDay();
            $names = Barber::active()->pluck('display_name', 'id');

            $slots = $this->availability
                ->slots($service, $barberId, $day, $day->copy()->endOfDay())
                ->map(fn (Slot $slot) => [
                    'starts_at' => $slot->startsAt->toIso8601String(),
                    'label' => $slot->startsAt->timezone($tz)->format('H:i'),
                    'barbers' => collect($slot->barberIds)
                        ->map(fn (int $id) => ['id' => $id, 'name' => $names[$id] ?? ''])
                        ->values(),
                ])
                ->values();
        }

        return response()->json([
            'days' => collect($counts)->map(fn (int $count, string $date) => [
                'date' => $date,
                'count' => $count,
            ])->values(),
            'slots' => $slots,
        ]);
    }
}
