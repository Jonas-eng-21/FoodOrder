<?php

namespace App\Patterns\Strategy;

use App\Exceptions\InvalidPaymentException;

/**
 * Strategy pattern — Concrete Strategy: card keeps the subtotal (no discount, fee or change).
 * No card data is ever handled.
 */
final class CardPaymentStrategy implements PaymentStrategy
{
    public function calculate(int $subtotalCents, ?int $receivedCents): PaymentQuote
    {
        if ($subtotalCents < 0) {
            throw InvalidPaymentException::negativeAmount();
        }

        return new PaymentQuote($subtotalCents, $subtotalCents, 0);
    }
}
