<?php

namespace App\Http\Controllers;

use App\Actions\CancelAppointment;
use App\Actions\CreateCharge;
use App\Actions\ReserveAppointment;
use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    /** Passo 04 → 05: prende o horário, abre a cobrança e devolve o token da tela de pagamento. */
    public function store(StoreAppointmentRequest $request, ReserveAppointment $reserve, CreateCharge $charge): JsonResponse
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
                    'email' => $data['email'] ?? null,
                    'document' => $data['document'],
                    'note' => $data['note'] ?? null,
                ],
            );
        } catch (SlotUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        try {
            $charge->handle($appointment, $data['billing_type']);
        } catch (RequestException $exception) {
            // a reserva fica pendente e expira sozinha; o cliente tenta de novo
            Log::error('Falha ao abrir cobrança no Asaas', [
                'appointment' => $appointment->id,
                'response' => $exception->response->body(),
            ]);

            return response()->json(['message' => 'Não conseguimos abrir a cobrança agora. Tente de novo em instantes.'], 502);
        }

        return response()->json([
            'token' => $appointment->public_token,
            'redirect' => route('appointments.show', $appointment->public_token),
        ], 201);
    }

    /** Tela 05/06: pagamento enquanto pendente, confirmação depois. */
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

    /** Polling do passo 05 — o webhook pode demorar alguns segundos. */
    public function status(string $token): JsonResponse
    {
        $appointment = $this->find($token);

        return response()->json([
            'status' => $appointment->status->value,
            'label' => $appointment->status->label(),
            'reserved_until' => $appointment->reserved_until?->toIso8601String(),
        ]);
    }

    public function cancel(string $token, CancelAppointment $cancel): RedirectResponse
    {
        $appointment = $this->find($token);

        if (! $appointment->isCancelableByCustomer()) {
            return back()->with('error', 'Esse agendamento não pode mais ser cancelado pelo site.');
        }

        $cancel->handle($appointment, reason: 'Cancelado pelo cliente', refund: true);

        return back()->with('success', 'Agendamento cancelado. O estorno cai na sua conta em alguns dias.');
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
        return Appointment::with(['barber', 'service', 'customer', 'payment'])
            ->where('public_token', $token)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(Appointment $appointment): array
    {
        $tz = config('barbearia.timezone');
        $payment = $appointment->payment;

        return [
            'token' => $appointment->public_token,
            'code' => $appointment->code(),
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'date' => $appointment->starts_at->timezone($tz)->toDateString(),
            'time' => $appointment->starts_at->timezone($tz)->format('H:i'),
            'reserved_until' => $appointment->reserved_until?->toIso8601String(),
            'price_cents' => $appointment->price_cents,
            'duration_min' => $appointment->duration_min,
            'service' => $appointment->service->name,
            'barber' => $appointment->barber->display_name,
            'customer' => $appointment->customer->name,
            'cancelable' => $appointment->isCancelableByCustomer(),
            'payment' => $payment === null ? null : [
                'billing_type' => $payment->billing_type,
                'invoice_url' => $payment->invoice_url,
                'pix_payload' => $payment->pix_payload,
                'pix_qr_base64' => $payment->pix_qr_base64,
            ],
        ];
    }
}
