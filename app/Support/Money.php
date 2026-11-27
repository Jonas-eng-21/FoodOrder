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
}
