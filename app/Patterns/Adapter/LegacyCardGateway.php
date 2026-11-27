<?php

namespace App\Patterns\Adapter;

/**
 * Adapter pattern — Adaptee. Simulates a pre-existing card API: receives a decimal string and answers
 * with a pipe-delimited string ("APPROVED|<reference>|<amount>" or "DECLINED|<reason>"). Local and offline.
 */
class LegacyCardGateway
{
    public function __construct(private readonly bool $reject = false) {}

    public function charge(string $amountDecimal): string
    {
        if ($this->reject) {
            return 'DECLINED|Cartão recusado pelo simulador.';
        }

        return 'APPROVED|SIMULATED-CARD-'.strtoupper(bin2hex(random_bytes(6))).'|'.$amountDecimal;
    }
}
