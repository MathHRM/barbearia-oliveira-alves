<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id', 'provider', 'provider_payment_id', 'billing_type',
        'amount_cents', 'status', 'invoice_url', 'pix_payload', 'pix_qr_base64',
        'paid_at', 'refunded_at', 'refund_amount_cents', 'raw',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'paid_at' => UtcDateTime::class,
            'refunded_at' => UtcDateTime::class,
            'amount_cents' => 'integer',
            'raw' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
