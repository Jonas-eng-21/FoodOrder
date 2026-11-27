<?php

namespace App\Patterns\Strategy;

use App\Exceptions\InvalidPaymentException;

/**
 * Strategy pattern — Strategy.
 * Common contract of the payment calculation algorithms. Implementations are pure:
 * they neither persist data nor process payments.
 */
interface PaymentStrategy
{
    /**
     * @throws InvalidPaymentException when the amounts are invalid for the method
     */
    public function calculate(int $subtotalCents, ?int $receivedCents): PaymentQuote;
}
