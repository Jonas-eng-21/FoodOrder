<?php

namespace App\Patterns\Adapter;

use App\Exceptions\InvalidPaymentException;

/**
 * Standardized outcome of a simulated payment processing (not a calculation: see PaymentQuote).
 * An approved result always has a reference and no error; a rejected one has an error and no reference.
 */
final readonly class PaymentResult
{
    public function __construct(
        public bool $approved,
        public ?string $reference = null,
        public ?string $error = null,
    ) {
        $hasReference = $reference !== null && trim($reference) !== '';
        $hasError = $error !== null && trim($error) !== '';

        if ($approved ? (! $hasReference || $error !== null) : (! $hasError || $reference !== null)) {
            throw InvalidPaymentException::inconsistentResult();
        }
    }

    public static function approved(string $reference): self
    {
        return new self(true, $reference);
    }

    public static function rejected(string $error): self
    {
        return new self(false, null, $error);
    }
}
