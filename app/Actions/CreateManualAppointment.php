<?php

namespace App\Actions;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Support\Phone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agendamento de balcão: entra já confirmado, sem gateway.
 * O pagamento acontece no caixa — quem garante que não pisa em outro horário
 * continua sendo a constraint EXCLUDE.
 */
class CreateManualAppointment
{
    public function handle(Service $service, int $barberId, Carbon $startsAt, array $customer): Appointment
    {
        $startsAt = $startsAt->copy()->utc();

        try {
            return DB::transaction(function () use ($service, $barberId, $startsAt, $customer) {
                $record = $this->upsertCustomer($customer);

                return Appointment::create([
                    'customer_id' => $record->id,
                    'barber_id' => $barberId,
                    'service_id' => $service->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMinutes($service->duration_min),
                    'status' => AppointmentStatus::Confirmed,
                    'origin' => AppointmentOrigin::Manual,
                    'price_cents' => $service->price_cents,
                    'duration_min' => $service->duration_min,
                    'customer_note' => $customer['note'] ?? null,
                    'confirmed_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'appointments_no_overlap')) {
                throw new SlotUnavailableException('Esse barbeiro já tem alguém nesse horário.');
            }

            throw $exception;
        }
    }

    private function upsertCustomer(array $customer): Customer
    {
        $phone = Phone::e164($customer['phone']);

        $record = Customer::firstOrNew(['phone_e164' => $phone]);
        $record->name = $customer['name'];
        $record->first_seen_at ??= now();
        $record->save();

        return $record;
    }
}
