<?php

namespace App\Actions;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Support\Document;
use App\Support\Phone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Prende o horário antes do pagamento: cria o agendamento em `pending_payment`
 * com TTL. Quem garante a exclusividade é a constraint EXCLUDE — a checagem de
 * disponibilidade aqui é só para devolver erro amigável no caso comum.
 */
class ReserveAppointment
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array{name: string, phone: string, email: ?string, document: ?string, note: ?string}  $customer
     *
     * @throws SlotUnavailableException
     */
    public function handle(Service $service, ?int $barberId, Carbon $startsAt, array $customer): Appointment
    {
        $startsAt = $startsAt->copy()->utc();

        $barberId = $barberId === null
            ? $this->availability->pickBarber($service, $startsAt)
            : ($this->availability->isFree($service, $barberId, $startsAt) ? $barberId : null);

        if ($barberId === null) {
            throw new SlotUnavailableException;
        }

        try {
            return DB::transaction(function () use ($service, $barberId, $startsAt, $customer) {
                $record = $this->upsertCustomer($customer);

                return Appointment::create([
                    'customer_id' => $record->id,
                    'barber_id' => $barberId,
                    'service_id' => $service->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMinutes($service->duration_min),
                    'status' => AppointmentStatus::PendingPayment,
                    'origin' => AppointmentOrigin::Online,
                    'price_cents' => $service->price_cents,
                    'duration_min' => $service->duration_min,
                    'customer_note' => $customer['note'] ?? null,
                    'reserved_until' => now()->addMinutes((int) config('barbearia.reservation_ttl_min')),
                ]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'appointments_no_overlap')) {
                throw new SlotUnavailableException;
            }

            throw $exception;
        }
    }

    /** Sem login: o telefone em E.164 é a identidade do cliente. */
    private function upsertCustomer(array $data): Customer
    {
        $phone = Phone::e164($data['phone']);

        $customer = Customer::firstOrNew(['phone_e164' => $phone]);
        $customer->name = $data['name'];
        $customer->email = $data['email'] ?? $customer->email;
        $customer->document = Document::digits((string) ($data['document'] ?? '')) ?: $customer->document;
        $customer->first_seen_at ??= now();
        $customer->save();

        return $customer;
    }
}
