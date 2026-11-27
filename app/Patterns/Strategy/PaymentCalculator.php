<?php

namespace App\Patterns\Strategy;

use App\Exceptions\InvalidPaymentException;

/**
 * Strategy pattern — Context.
 * Selects the strategy for a payment method and delegates the calculation to it
 * through the PaymentStrategy interface. It contains no payment algorithm.
 */
final class PaymentCalculator
{
    /** @param array<string, PaymentStrategy> $strategies available strategies keyed by payment method */
    public function __construct(private readonly array $strategies) {}

    /**
     * @throws InvalidPaymentException for unsupported methods or invalid amounts
     */
    public function calculate(string $method, int $subtotalCents, ?int $receivedCents = null): PaymentQuote
    {
        $strategy = $this->strategies[$method] ?? throw InvalidPaymentException::unsupportedMethod($method);

        return $strategy->calculate($subtotalCents, $receivedCents);
    }
}
