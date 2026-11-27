<?php

namespace App\Patterns\Adapter;

/**
 * Implements the Target directly: cash has no incompatible legacy API to adapt.
 * Validation of the received amount and the change belong to CashPaymentStrategy.
 */
final class CashPaymentGateway implements PaymentGateway
{
    public function charge(int $totalCents): PaymentResult
    {
        return PaymentResult::approved('SIMULATED-CASH-'.strtoupper(bin2hex(random_bytes(6))));
    }
}
