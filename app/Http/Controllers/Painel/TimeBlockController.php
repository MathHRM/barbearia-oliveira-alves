<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\TimeBlock;
use App\Support\PainelScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Bloqueio pontual ou de período: some da agenda pública assim que salva. */
class TimeBlockController extends Controller
{
    /** teto do período: férias longas ainda cabem, dedo escorregado não vira mil linhas */
    private const MAX_DAYS = 90;

    public function store(Request $request): RedirectResponse
    {
        $scope = PainelScope::for($request->user());

        $validated = $request->validate([
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'starts' => ['required', 'date_format:H:i'],
            'ends' => ['required', 'date_format:H:i', 'after:starts'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        $barberId = $scope->isOwner() ? ($validated['barber_id'] ?? null) : $scope->ownBarberId();

        abort_if($barberId === null, 403);

        $tz = config('barbearia.timezone');
        $first = Carbon::parse($validated['date'], $tz);
        $last = Carbon::parse($validated['until'] ?? $validated['date'], $tz);
        $days = (int) $first->diffInDays($last) + 1;

        if ($days > self::MAX_DAYS) {
            return back()->withErrors(['until' => 'O período não pode passar de '.self::MAX_DAYS.' dias.']);
        }

        $periodId = (string) Str::uuid();

        DB::transaction(function () use ($first, $days, $barberId, $periodId, $validated, $request, $tz) {
            for ($offset = 0; $offset < $days; $offset++) {
                $day = $first->copy()->addDays($offset)->format('Y-m-d');

                TimeBlock::create([
                    'barber_id' => $barberId,
                    'period_id' => $periodId,
                    'starts_at' => Carbon::parse($day.' '.$validated['starts'], $tz),
                    'ends_at' => Carbon::parse($day.' '.$validated['ends'], $tz),
                    'reason' => $validated['reason'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', $days === 1 ? 'Horário bloqueado.' : "{$days} dias bloqueados.");
    }

    public function destroy(Request $request, TimeBlock $block): RedirectResponse
    {
        $scope = PainelScope::for($request->user());

        abort_unless($scope->isOwner() || $block->barber_id === $scope->ownBarberId(), 403);

        // a lista mostra o período inteiro numa linha; remover tem que casar com o que se vê
        $removed = $block->period_id
            ? TimeBlock::where('period_id', $block->period_id)->delete()
            : $block->delete();

        return back()->with('success', $removed > 1 ? 'Período desbloqueado.' : 'Bloqueio removido.');
    }
}
