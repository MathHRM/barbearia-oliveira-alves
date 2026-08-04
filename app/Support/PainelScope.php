<?php

namespace App\Support;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Quem o usuário logado enxerga no painel.
 * `owner` vê a barbearia inteira; `barber` só a própria agenda — e nunca faturamento.
 */
class PainelScope
{
    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    public function isOwner(): bool
    {
        return $this->user->isOwner();
    }

    /** Barbeiro amarrado ao usuário logado (null para o dono, que não atende). */
    public function ownBarberId(): ?int
    {
        return $this->user->barber?->id;
    }

    /** @return Collection<int, Barber> barbeiros que aparecem nos filtros */
    public function barbers(): Collection
    {
        return Barber::query()
            ->when(! $this->isOwner(), fn ($query) => $query->whereKey($this->ownBarberId()))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Traduz o filtro da tela para o barbeiro efetivo.
     * O `barber` ignora o que veio na URL: sempre a própria agenda.
     */
    public function resolveBarberId(?int $requested): ?int
    {
        if (! $this->isOwner()) {
            return $this->ownBarberId() ?? -1;
        }

        return $requested;
    }

    public function canSeeRevenue(): bool
    {
        return $this->isOwner();
    }
}
