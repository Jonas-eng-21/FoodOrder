<?php

namespace App\Patterns\Adapter;

use App\Exceptions\InvalidPaymentException;

/**
 * Adapter pattern — Adapter. Wraps LegacyPixGateway, forwards the amount in cents and translates
 * the legacy array into a PaymentResult. Anything other than a well-formed confirmation is a rejection.
 */
final class PixGatewayAdapter implements PaymentGateway
{
    public function __construct(private readonly LegacyPixGateway $legacy) {}

    public function charge(int $totalCents): PaymentResult
    {
        if ($totalCents < 0) {
            throw InvalidPaymentException::negativeAmount();
        }

        $response = $this->legacy->sendPix($totalCents);
        $status = $response['status'] ?? null;

        if ($status === 'DENIED') {
            $reason = $response['reason'] ?? null;

            return PaymentResult::rejected(is_string($reason) && trim($reason) !== '' ? $reason : 'PIX recusado.');
        }

        $txid = $response['txid'] ?? null;
        if ($status === 'CONFIRMED' && is_string($txid) && trim($txid) !== '' && ($response['amount_cents'] ?? null) === $totalCents) {
            return PaymentResult::approved($txid);
        }

        return PaymentResult::rejected('Resposta inválida do gateway PIX simulado.');
    }
}
