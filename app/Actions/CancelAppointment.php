<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Asaas\AsaasClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/** Cancela e, quando pedido, manda o estorno integral para o Asaas. */
class CancelAppointment
{
    public function __construct(private readonly AsaasClient $asaas) {}

    public function handle(Appointment $appointment, string $reason, bool $refund, ?User $by = null): Appointment
    {
        $payment = $appointment->payment;

        if ($refund && $payment?->status === PaymentStatus::Confirmed && $payment->provider_payment_id) {
            try {
                $this->asaas->refund($payment->provider_payment_id);
                $payment->update([
                    'status' => PaymentStatus::Refunded,
                    'refunded_at' => now(),
                    'refund_amount_cents' => $payment->amount_cents,
                ]);
            } catch (RequestException $exception) {
                // o horário volta para a agenda de qualquer jeito; o estorno vira tarefa manual
                Log::error('Falha ao estornar no Asaas', [
                    'payment' => $payment->id,
                    'response' => $exception->response->body(),
                ]);
            }
        }

        $appointment->update([
            'status' => AppointmentStatus::Canceled,
            'canceled_at' => now(),
            'cancel_reason' => $reason,
            'canceled_by' => $by?->id,
            'reserved_until' => null,
        ]);

        return $appointment;
    }
}
