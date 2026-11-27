<?php

namespace App\Patterns\Strategy;

use App\Exceptions\InvalidPaymentException;

/**
 * Strategy pattern — Concrete Strategy: PIX pays 5% less (RN04), rounded to the
 * nearest cent with half a cent rounded up, using integer arithmetic only.
 */
final class PixPaymentStrategy implements PaymentStrategy
{
    public function calculate(int $subtotalCents, ?int $receivedCents): PaymentQuote
    {
        if ($subtotalCents < 0) {
            throw InvalidPaymentException::negativeAmount();
        }
        if ($subtotalCents > intdiv(PHP_INT_MAX - 50, 95)) {
            throw InvalidPaymentException::amountTooLarge();
        }

        $totalCents = intdiv($subtotalCents * 95 + 50, 100);

        return new PaymentQuote($subtotalCents, $totalCents, $subtotalCents - $totalCents);
    }
}
