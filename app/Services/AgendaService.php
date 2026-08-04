<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Monta a agenda de um dia: linhas, bloqueios e os números do cabeçalho. */
class AgendaService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /** @return array{0: Carbon, 1: Carbon} início e fim do dia, no fuso da barbearia */
    public function dayWindow(string $date): array
    {
        $day = Carbon::parse($date, config('barbearia.timezone'));

        return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rows(string $date, ?int $barberId): Collection
    {
        [$from, $to] = $this->dayWindow($date);

        return Appointment::query()
            ->with(['customer', 'barber', 'service', 'payment'])
            ->whereBetween('starts_at', [$from, $to])
            ->when($barberId, fn ($query) => $query->where('barber_id', $barberId))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => $this->present($appointment));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function blocks(string $date, ?int $barberId): Collection
    {
        [$from, $to] = $this->dayWindow($date);

        return TimeBlock::query()
            ->with(['barber', 'creator'])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($barberId, fn ($query) => $query->where('barber_id', $barberId))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (TimeBlock $block) => [
                'id' => $block->id,
                'barber' => $block->barber->display_name,
                'starts_at' => $this->local($block->starts_at)->format('H:i'),
                'ends_at' => $this->local($block->ends_at)->format('H:i'),
                'reason' => $block->reason,
                'created_by' => $block->creator?->name,
                'created_at' => $this->local($block->created_at)->format('d/m H:i'),
            ]);
    }

    /**
     * "Hoje em números". Receita só entra na tela do dono — o scope decide.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function totals(Collection $rows, string $date, ?int $barberId): array
    {
        $by = fn (string $status) => $rows->where('status', $status);

        return [
            'total' => $rows->count(),
            'confirmed' => $by(AppointmentStatus::Confirmed->value)->count(),
            'pending' => $by(AppointmentStatus::PendingPayment->value)->count(),
            'attended' => $by(AppointmentStatus::Attended->value)->count(),
            'canceled' => $by(AppointmentStatus::Canceled->value)->count()
                + $by(AppointmentStatus::NoShow->value)->count(),
            // previsto = todo o dinheiro do dia, menos o que caiu (cancelado/expirado)
            'expected_cents' => (int) $rows->whereIn('status', [
                AppointmentStatus::PendingPayment->value,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Attended->value,
                AppointmentStatus::NoShow->value,
            ])->sum('price_cents'),
            // recebido = quem sentou na cadeira + cancelamento cujo pagamento não foi estornado
            'received_cents' => (int) $rows->filter(fn (array $row) => $row['status'] === AppointmentStatus::Attended->value
                || ($row['status'] === AppointmentStatus::Canceled->value && $row['paid']))->sum('price_cents'),
            'free_slots' => $this->freeSlots($date, $barberId),
        ];
    }

    /** Quantos horários ainda dá para vender no dia, medidos pelo serviço mais curto. */
    private function freeSlots(string $date, ?int $barberId): int
    {
        $service = Service::active()->orderBy('duration_min')->first();

        if ($service === null) {
            return 0;
        }

        [$from, $to] = $this->dayWindow($date);

        return $this->availability->slots($service, $barberId, $from, $to)->count();
    }

    /** @return array<string, mixed> */
    private function present(Appointment $appointment): array
    {
        $customer = $appointment->customer;
        $payment = $appointment->payment;

        return [
            'id' => $appointment->id,
            'code' => $appointment->code(),
            'starts_at' => $this->local($appointment->starts_at)->format('H:i'),
            'ends_at' => $this->local($appointment->ends_at)->format('H:i'),
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'tone' => $appointment->status->tone(),
            'origin' => $appointment->origin->label(),
            'service' => $appointment->service->name,
            'duration_min' => $appointment->duration_min,
            'price_cents' => $appointment->price_cents,
            'barber' => $appointment->barber->display_name,
            'barber_id' => $appointment->barber_id,
            'note' => $appointment->customer_note,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => Phone::format($customer->phone_e164),
                'visits' => $customer->appointments()->where('status', AppointmentStatus::Attended)->count(),
            ],
            'payment' => $payment === null ? null : [
                'billing_type' => $payment->billing_type,
                'status' => $payment->status->value,
                'refundable' => $payment->status === PaymentStatus::Confirmed,
            ],
            'paid' => $payment?->status === PaymentStatus::Confirmed
                || ($payment === null && $appointment->status === AppointmentStatus::Attended),
            // presença/falta só depois do horário começar — ninguém compareceu a algo que não aconteceu
            'can_attend' => $appointment->starts_at->isPast()
                && in_array($appointment->status, [AppointmentStatus::Confirmed, AppointmentStatus::NoShow], true),
            'can_no_show' => $appointment->starts_at->isPast()
                && $appointment->status === AppointmentStatus::Confirmed,
            'can_cancel' => in_array($appointment->status, AppointmentStatus::blocking(), true),
        ];
    }

    private function local(Carbon $moment): Carbon
    {
        return $moment->copy()->timezone(config('barbearia.timezone'));
    }
}
