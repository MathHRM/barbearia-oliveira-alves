<?php

namespace App\Http\Controllers\Painel;

use App\Actions\CancelAppointment;
use App\Actions\CreateManualAppointment;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Painel\StoreManualAppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Support\PainelScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    /** Lançamento de balcão, direto na agenda do dia. */
    public function store(StoreManualAppointmentRequest $request, CreateManualAppointment $action): RedirectResponse
    {
        $scope = PainelScope::for($request->user());
        $barberId = $scope->isOwner() ? $request->integer('barber_id') : $scope->ownBarberId();

        abort_if($barberId === null, 403);

        $service = Service::findOrFail($request->integer('service_id'));
        $startsAt = Carbon::parse($request->string('date').' '.$request->string('time'), config('barbearia.timezone'));

        try {
            $action->handle($service, $barberId, $startsAt, $request->validated());
        } catch (SlotUnavailableException $exception) {
            return back()->withErrors(['time' => $exception->getMessage()]);
        }

        return back()->with('success', 'Agendamento lançado.');
    }

    public function attended(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeManage($request, $appointment);

        if ($appointment->starts_at->isFuture()) {
            return back()->with('error', 'Esse horário ainda não chegou.');
        }

        $appointment->update(['status' => AppointmentStatus::Attended, 'attended_at' => now()]);
        $appointment->customer->update(['last_visit_at' => $appointment->starts_at]);

        return back()->with('success', 'Marcado como compareceu.');
    }

    public function noShow(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeManage($request, $appointment);

        if ($appointment->starts_at->isFuture()) {
            return back()->with('error', 'Esse horário ainda não chegou.');
        }

        $appointment->update(['status' => AppointmentStatus::NoShow, 'attended_at' => null]);

        return back()->with('success', 'Marcado como falta.');
    }

    /** O estorno é decisão do barbeiro — nada é devolvido sem o check na tela. */
    public function cancel(Request $request, Appointment $appointment, CancelAppointment $action): RedirectResponse
    {
        $this->authorizeManage($request, $appointment);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
            'refund' => ['boolean'],
        ]);

        $action->handle(
            $appointment,
            $validated['reason'] ?? 'Cancelado pelo painel',
            (bool) ($validated['refund'] ?? false),
            $request->user(),
        );

        return back()->with('success', 'Agendamento cancelado.');
    }

    private function authorizeManage(Request $request, Appointment $appointment): void
    {
        abort_unless($request->user()->can('manage', $appointment), 403);
    }
}
