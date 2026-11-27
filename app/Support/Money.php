<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    /**
     * Formats an amount in cents as reais (e.g. 2000 => "R$ 20,00") using integer arithmetic only.
     */
    public static function format(int $cents): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Monetary amounts cannot be negative.');
        }

        return sprintf('R$ %s,%02d', number_format(intdiv($cents, 100), 0, '', '.'), $cents % 100);
    }

    /**
     * Parses a reais amount typed by the operator ("30", "30.5", "30,50") into cents without floats.
     * At most 7 integer digits and 2 decimals; signs, exponents and separators of thousands are rejected.
     */
    public static function parseReais(string $input): int
    {
        if (preg_match('/^(\d{1,7})(?:[.,](\d{1,2}))?\z/', $input, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
