<?php

namespace App\Support;

/**
 * Telefone é a identidade do cliente (não tem login), então precisa de uma forma
 * canônica: E.164 brasileiro. "(11) 98888-7777" e "+5511988887777" viram a mesma chave.
 */
class Phone
{
    public static function e164(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        // já veio com código do país
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return '+'.$digits;
        }

        return '+55'.$digits;
    }

    public static function isValid(string $raw): bool
    {
        // +55 + DDD (2) + 8 ou 9 dígitos
        return (bool) preg_match('/^\+55\d{10,11}$/', self::e164($raw));
    }

    /** "+5511988887777" → "(11) 98888-7777" */
    public static function format(string $e164): string
    {
        $digits = substr(preg_replace('/\D/', '', $e164) ?? '', 2);

        if (strlen($digits) < 10) {
            return $e164;
        }

        $area = substr($digits, 0, 2);
        $rest = substr($digits, 2);
        $split = strlen($rest) - 4;

        return sprintf('(%s) %s-%s', $area, substr($rest, 0, $split), substr($rest, $split));
    }
}
