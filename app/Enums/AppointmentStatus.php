<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Attended = 'attended';
    case NoShow = 'no_show';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /** Status que seguram o slot — os mesmos da constraint EXCLUDE no banco. */
    public static function blocking(): array
    {
        return [self::Scheduled, self::Attended];
    }

    public static function blockingValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::blocking());
    }

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::Attended => 'Compareceu',
            self::NoShow => 'Faltou',
            self::Canceled => 'Cancelado',
            self::Expired => 'Expirado',
        };
    }

    /** Cor semântica usada nos badges do painel. */
    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'warning',
            self::Attended => 'success',
            self::Canceled, self::NoShow, self::Expired => 'danger',
        };
    }
}
