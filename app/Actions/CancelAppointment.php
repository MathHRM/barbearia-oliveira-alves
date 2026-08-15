<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;

/** Cancela o horário; não há pagamento online nem estorno automático. */
class CancelAppointment
{
    public function handle(Appointment $appointment, string $reason, ?User $by = null): Appointment
    {

        $appointment->update([
            'status' => AppointmentStatus::Canceled,
            'canceled_at' => now(),
            'cancel_reason' => $reason,
            'canceled_by' => $by?->id,
        ]);

        return $appointment;
    }
}
