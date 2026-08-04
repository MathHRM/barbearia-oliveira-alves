<?php

namespace App\Services\Asaas;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Traduz o evento do Asaas em mudança de estado. Idempotente por natureza:
 * cada transição só acontece se o agendamento ainda estiver no estado anterior.
 */
class ProcessWebhookEvent
{
    public function handle(string $event, array $payload): void
    {
        $charge = $payload['payment'] ?? [];
        $payment = Payment::where('provider_payment_id', $charge['id'] ?? '')->first();

        if ($payment === null) {
            return;
        }

        $appointment = $payment->appointment;

        match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->confirm($appointment, $payment, $charge),
            'PAYMENT_REFUNDED' => $this->refund($appointment, $payment),
            'PAYMENT_OVERDUE' => $this->expire($appointment, $payment),
            default => null,
        };
    }

    private function confirm(Appointment $appointment, Payment $payment, array $charge): void
    {
        DB::transaction(function () use ($appointment, $payment, $charge) {
            $payment->update([
                'status' => PaymentStatus::Confirmed,
                'paid_at' => now(),
                'raw' => $charge,
            ]);

            if ($appointment->status !== AppointmentStatus::PendingPayment) {
                return;
            }

            $appointment->update([
                'status' => AppointmentStatus::Confirmed,
                'confirmed_at' => now(),
                'reserved_until' => null,
            ]);
        });
    }

    private function refund(Appointment $appointment, Payment $payment): void
    {
        DB::transaction(function () use ($appointment, $payment) {
            $payment->update([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
                'refund_amount_cents' => $payment->amount_cents,
            ]);

            if (in_array($appointment->status, [AppointmentStatus::Canceled, AppointmentStatus::Attended], true)) {
                return;
            }

            $appointment->update([
                'status' => AppointmentStatus::Canceled,
                'canceled_at' => now(),
                'cancel_reason' => 'Estorno do pagamento',
                'reserved_until' => null,
            ]);
        });
    }

    private function expire(Appointment $appointment, Payment $payment): void
    {
        $payment->update(['status' => PaymentStatus::Overdue]);

        if ($appointment->status === AppointmentStatus::PendingPayment) {
            $appointment->update(['status' => AppointmentStatus::Expired, 'reserved_until' => null]);
        }
    }
}
