<?php

namespace App\Enums;

enum AppointmentOrigin: string
{
    case Online = 'online';   // veio do site, com pagamento pelo gateway
    case Manual = 'manual';   // balcão ou telefone, lançado pelo barbeiro

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Manual => 'Balcão',
        };
    }
}
