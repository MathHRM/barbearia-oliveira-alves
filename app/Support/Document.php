<?php

namespace App\Support;

/** CPF: exigido pelo Asaas para criar o cliente da cobrança. */
class Document
{
    public static function digits(string $raw): string
    {
        return preg_replace('/\D/', '', $raw) ?? '';
    }

    public static function isValidCpf(string $raw): bool
    {
        $cpf = self::digits($raw);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $position) {
            $sum = 0;

            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $cpf[$i] * ($position + 1 - $i);
            }

            $digit = (($sum * 10) % 11) % 10;

            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function format(string $raw): string
    {
        $cpf = self::digits($raw);

        return strlen($cpf) === 11
            ? substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9)
            : $raw;
    }
}
