<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\TimeBlock;
use App\Support\PainelScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Bloqueio pontual: some da agenda pública assim que salva. */
class TimeBlockController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $scope = PainelScope::for($request->user());

        $validated = $request->validate([
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'starts' => ['required', 'date_format:H:i'],
            'ends' => ['required', 'date_format:H:i', 'after:starts'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        $barberId = $scope->isOwner() ? ($validated['barber_id'] ?? null) : $scope->ownBarberId();

        abort_if($barberId === null, 403);

        $tz = config('barbearia.timezone');

        TimeBlock::create([
            'barber_id' => $barberId,
            'starts_at' => Carbon::parse($validated['date'].' '.$validated['starts'], $tz),
            'ends_at' => Carbon::parse($validated['date'].' '.$validated['ends'], $tz),
            'reason' => $validated['reason'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Horário bloqueado.');
    }

    public function destroy(Request $request, TimeBlock $block): RedirectResponse
    {
        $scope = PainelScope::for($request->user());

        abort_unless($scope->isOwner() || $block->barber_id === $scope->ownBarberId(), 403);

        $block->delete();

        return back()->with('success', 'Bloqueio removido.');
    }
}
