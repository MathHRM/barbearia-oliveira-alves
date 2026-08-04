<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'appointments:expire';

    protected $description = 'Solta os horários de reservas que passaram do prazo sem pagamento';

    public function handle(): int
    {
        $expired = Appointment::query()
            ->where('status', AppointmentStatus::PendingPayment)
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->update(['status' => AppointmentStatus::Expired, 'reserved_until' => null]);

        $this->info("{$expired} reserva(s) expirada(s).");

        return self::SUCCESS;
    }
}
