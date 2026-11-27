<?php

namespace App\Patterns\Adapter;

/**
 * Adapter pattern — Adaptee. Simulates a pre-existing PIX API: receives cents and answers with
 * its own array format. Local and offline; the outcome is deterministic (approved unless configured).
 */
class LegacyPixGateway
{
    public function __construct(private readonly bool $reject = false) {}

    /**
     * @return array{status: string, txid?: string, amount_cents?: int, reason?: string}
     */
    public function sendPix(int $amountCents): array
    {
        if ($this->reject) {
            return ['status' => 'DENIED', 'reason' => 'PIX recusado pelo simulador.'];
        }

        return [
            'status' => 'CONFIRMED',
            'txid' => 'SIMULATED-PIX-'.strtoupper(bin2hex(random_bytes(6))),
            'amount_cents' => $amountCents,
        ];
    }
}
