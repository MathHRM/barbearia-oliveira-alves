<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Support\PainelScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Grade semanal de expediente. É a base da disponibilidade pública —
 * mudou aqui, a agenda do site muda na próxima consulta.
 */
class WorkingHourController extends Controller
{
    private const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    public function index(Request $request): Response
    {
        $scope = PainelScope::for($request->user());

        return Inertia::render('painel/horarios', [
            'weekdays' => self::WEEKDAYS,
            'barbers' => $scope->barbers()->load('workingHours')->map(fn (Barber $barber) => [
                'id' => $barber->id,
                'name' => $barber->display_name,
                'active' => $barber->active,
                'hours' => $barber->workingHours
                    ->sortBy('weekday')
                    ->map(fn ($hour) => [
                        'weekday' => $hour->weekday,
                        // o Postgres devolve `time` com segundos; a tela usa HH:MM
                        'starts_at' => substr($hour->starts_at, 0, 5),
                        'ends_at' => substr($hour->ends_at, 0, 5),
                    ])->values(),
            ])->values(),
        ]);
    }

    /** Salva a semana inteira de um barbeiro de uma vez: dia sem faixa vira folga. */
    public function update(Request $request, Barber $barber): RedirectResponse
    {
        $scope = PainelScope::for($request->user());

        abort_unless($scope->isOwner() || $barber->id === $scope->ownBarberId(), 403);

        $validated = $request->validate([
            'hours' => ['present', 'array'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'hours.*.starts_at' => ['required', 'date_format:H:i'],
            'hours.*.ends_at' => ['required', 'date_format:H:i', 'after:hours.*.starts_at'],
        ]);

        DB::transaction(function () use ($barber, $validated) {
            $barber->workingHours()->delete();

            foreach ($validated['hours'] as $hour) {
                $barber->workingHours()->create($hour);
            }
        });

        return back()->with('success', 'Grade salva.');
    }
}
