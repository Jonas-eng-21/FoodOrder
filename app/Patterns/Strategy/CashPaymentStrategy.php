<?php

namespace App\Patterns\Strategy;

use App\Exceptions\InvalidPaymentException;

/**
 * Strategy pattern — Concrete Strategy: cash keeps the subtotal, requires a sufficient
 * received amount and returns the change.
 */
final class CashPaymentStrategy implements PaymentStrategy
{
    public function calculate(int $subtotalCents, ?int $receivedCents): PaymentQuote
    {
        if ($subtotalCents < 0) {
            throw InvalidPaymentException::negativeAmount();
        }
        if ($receivedCents === null) {
            throw InvalidPaymentException::receivedAmountRequired();
        }
        if ($receivedCents < $subtotalCents) {
            throw InvalidPaymentException::insufficientReceivedAmount();
        }

        return new PaymentQuote($subtotalCents, $subtotalCents, 0, $receivedCents, $receivedCents - $subtotalCents);
    }
}
