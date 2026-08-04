<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Intervalo meio-aberto [start, end): o fim de um slot encosta no início do próximo sem conflitar. */
readonly class TimeRange
{
    public function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {}

    public function overlaps(self $other): bool
    {
        return $this->start->lt($other->end) && $this->end->gt($other->start);
    }

    public function contains(self $other): bool
    {
        return $this->start->lte($other->start) && $this->end->gte($other->end);
    }

    public function isEmpty(): bool
    {
        return $this->start->gte($this->end);
    }
}
