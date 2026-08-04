<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';     // agenda de todos + financeiro + cadastros
    case Barber = 'barber';   // só a própria agenda

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dono',
            self::Barber => 'Barbeiro',
        };
    }
}
