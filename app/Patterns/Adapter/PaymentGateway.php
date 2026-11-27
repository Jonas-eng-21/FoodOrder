<?php

namespace App\Patterns\Adapter;

/**
 * Adapter pattern — Target.
 * The only contract the payment service knows; every gateway is simulated and local.
 */
interface PaymentGateway
{
    public function charge(int $totalCents): PaymentResult;
}
