<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';   // Asaas: PAYMENT_CONFIRMED / PAYMENT_RECEIVED
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Overdue = 'overdue';
}
