<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Um horário oferecido ao cliente. `barberIds` são todos os barbeiros livres
 * naquele instante — a variante "tanto faz" escolhe um deles na hora de reservar.
 */
readonly class Slot
{
    /** @param  list<int>  $barberIds */
    public function __construct(
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public array $barberIds,
    ) {}

    public function hasBarber(int $barberId): bool
    {
        return in_array($barberId, $this->barberIds, true);
    }
}
