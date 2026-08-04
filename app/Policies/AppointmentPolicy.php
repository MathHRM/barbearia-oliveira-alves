<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /** O barbeiro só mexe na própria agenda; o dono mexe em tudo. */
    public function manage(User $user, Appointment $appointment): bool
    {
        return $user->isOwner() || $appointment->barber_id === $user->barber?->id;
    }
}
