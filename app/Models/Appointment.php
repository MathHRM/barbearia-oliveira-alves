<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'barber_id', 'service_id',
        'starts_at', 'ends_at', 'status', 'origin',
        'price_cents', 'duration_min', 'customer_note',
        'reserved_until', 'confirmed_at', 'attended_at',
        'canceled_at', 'cancel_reason', 'canceled_by', 'public_token',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'reserved_until' => UtcDateTime::class,
            'confirmed_at' => UtcDateTime::class,
            'attended_at' => UtcDateTime::class,
            'canceled_at' => UtcDateTime::class,
            'status' => AppointmentStatus::class,
            'origin' => AppointmentOrigin::class,
            'price_cents' => 'integer',
            'duration_min' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $appointment) {
            $appointment->public_token ??= (string) Str::uuid();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** Agendamentos que ocupam o slot — espelha a constraint EXCLUDE. */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::blockingValues());
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    /** Reserva que estourou o TTL sem o pagamento voltar aprovado. */
    public function isReservationExpired(): bool
    {
        return $this->status === AppointmentStatus::PendingPayment
            && $this->reserved_until !== null
            && $this->reserved_until->isPast();
    }

    /** Cliente só cancela sozinho dentro da janela configurada. */
    public function isCancelableByCustomer(): bool
    {
        return $this->status === AppointmentStatus::Confirmed
            && $this->starts_at->gt(now()->addHours(config('barbearia.cancel_window_hours')));
    }

    public function code(): string
    {
        return '#OA-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function priceLabel(): string
    {
        return 'R$ '.number_format($this->price_cents / 100, 2, ',', '.');
    }
}
