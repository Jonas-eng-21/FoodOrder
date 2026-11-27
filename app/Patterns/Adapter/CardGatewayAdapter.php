<?php

namespace App\Patterns\Adapter;

use App\Exceptions\InvalidPaymentException;

/**
 * Adapter pattern — Adapter. Wraps LegacyCardGateway: converts cents to the decimal string it expects
 * (integer arithmetic only) and translates its textual answer into a PaymentResult.
 */
final class CardGatewayAdapter implements PaymentGateway
{
    public function __construct(private readonly LegacyCardGateway $legacy) {}

    public function charge(int $totalCents): PaymentResult
    {
        if ($totalCents < 0) {
            throw InvalidPaymentException::negativeAmount();
        }

        $amountDecimal = sprintf('%d.%02d', intdiv($totalCents, 100), $totalCents % 100);
        $parts = explode('|', $this->legacy->charge($amountDecimal));

        if (count($parts) === 2 && $parts[0] === 'DECLINED' && trim($parts[1]) !== '') {
            return PaymentResult::rejected($parts[1]);
        }

        if (count($parts) === 3 && $parts[0] === 'APPROVED' && trim($parts[1]) !== '' && $parts[2] === $amountDecimal) {
            return PaymentResult::approved($parts[1]);
        }

        return PaymentResult::rejected('Resposta inválida do gateway de cartão simulado.');
    }
}
