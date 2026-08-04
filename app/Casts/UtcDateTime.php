<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Colunas timestamptz sempre gravadas em UTC.
 *
 * O cast 'datetime' do Eloquent formata o Carbon sem offset, então um horário
 * criado em America/Sao_Paulo chegaria ao Postgres como se fosse UTC — três horas
 * de diferença silenciosa. Aqui a conversão é explícita nas duas pontas.
 *
 * @implements CastsAttributes<Carbon|null, mixed>
 */
class UtcDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->utc();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}
