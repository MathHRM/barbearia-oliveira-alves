<?php

namespace App\Http\Controllers;

use App\Actions\CancelAppointment;
use App\Actions\ReserveAppointment;
use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    /** Confirma imediatamente; o método de pagamento é apenas uma estimativa. */
    public function store(StoreAppointmentRequest $request, ReserveAppointment $reserve): JsonResponse
    {
        $data = $request->validated();
        $service = Service::active()->findOrFail($data['service_id']);

        try {
            $appointment = $reserve->handle(
                $service,
                $data['barber_id'] ?? null,
                Carbon::parse($data['starts_at']),
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'note' => $data['note'] ?? null,
                    'payment_method' => $data['payment_method'],
                ],
            );
        } catch (SlotUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'token' => $appointment->public_token,
            'redirect' => route('appointments.show', $appointment->public_token),
        ], 201);
    }

    /** Tela de confirmação do agendamento. */
    public function show(string $token): Response
    {
        $appointment = $this->find($token);

        return Inertia::render('agendar/acompanhamento', [
            'appointment' => $this->present($appointment),
            'shop' => [
                'name' => config('barbearia.name'),
                'address' => config('barbearia.address'),
                'cancel_window_hours' => (int) config('barbearia.cancel_window_hours'),
            ],
        ]);
    }

    public function cancel(string $token, CancelAppointment $cancel): RedirectResponse
    {
        $appointment = $this->find($token);

        if (! $appointment->isCancelableByCustomer()) {
            return back()->with('error', 'Esse agendamento não pode mais ser cancelado pelo site.');
        }

        $cancel->handle($appointment, reason: 'Cancelado pelo cliente');

        return back()->with('success', 'Agendamento cancelado.');
    }

    /** Evento para o calendário do cliente — horários em UTC, como manda o iCalendar. */
    public function ics(string $token): HttpResponse
    {
        $appointment = $this->find($token);
        $stamp = fn ($date) => $date->copy()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.config('barbearia.name').'//PT-BR',
            'BEGIN:VEVENT',
            'UID:'.$appointment->public_token.'@barbearia',
            'DTSTAMP:'.$stamp(now()),
            'DTSTART:'.$stamp($appointment->starts_at),
            'DTEND:'.$stamp($appointment->ends_at),
            'SUMMARY:'.$appointment->service->name.' · '.config('barbearia.name'),
            'DESCRIPTION:'.$appointment->code().' com '.$appointment->barber->display_name,
            'LOCATION:'.config('barbearia.address'),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="agendamento.ics"',
        ]);
    }

    private function find(string $token): Appointment
    {
        return Appointment::with(['barber', 'service', 'customer'])
            ->where('public_token', $token)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(Appointment $appointment): array
    {
        $tz = config('barbearia.timezone');
        return [
            'token' => $appointment->public_token,
            'code' => $appointment->code(),
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'date' => $appointment->starts_at->timezone($tz)->toDateString(),
            'time' => $appointment->starts_at->timezone($tz)->format('H:i'),
            'price_cents' => $appointment->price_cents,
            'duration_min' => $appointment->duration_min,
            'service' => $appointment->service->name,
            'barber' => $appointment->barber->display_name,
            'customer' => $appointment->customer->name,
            'cancelable' => $appointment->isCancelableByCustomer(),
            'payment_method' => $appointment->payment_method,
        ];
    }
}
