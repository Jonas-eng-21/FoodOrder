<?php

namespace App\Patterns\Strategy;

use App\Exceptions\InvalidPaymentException;

/**
 * Immutable result of a payment calculation (all amounts in integer cents).
 * It is a calculation result, not a persisted entity.
 */
final readonly class PaymentQuote
{
    public function __construct(
        public int $subtotalCents,
        public int $totalCents,
        public int $discountCents,
        public ?int $receivedCents = null,
        public ?int $changeCents = null,
    ) {
        foreach ([$subtotalCents, $totalCents, $discountCents, $receivedCents, $changeCents] as $amount) {
            if ($amount !== null && $amount < 0) {
                throw InvalidPaymentException::negativeAmount();
            }
        }

        if ($totalCents + $discountCents !== $subtotalCents) {
            throw InvalidPaymentException::inconsistentQuote();
        }

        $changeExpected = $receivedCents === null ? null : $receivedCents - $totalCents;
        if ($changeCents !== $changeExpected) {
            throw InvalidPaymentException::inconsistentQuote();
        }
    }
}
