<?php

namespace App\Exceptions;

use DomainException;

class InvalidPaymentException extends DomainException
{
    public static function negativeAmount(): self
    {
        return new self('Monetary amounts cannot be negative.');
    }

    public static function amountTooLarge(): self
    {
        return new self('The amount is too large to be calculated safely.');
    }

    public static function receivedAmountRequired(): self
    {
        return new self('A received amount is required for cash payments.');
    }

    public static function insufficientReceivedAmount(): self
    {
        return new self('The received amount is lower than the total.');
    }

    public static function inconsistentQuote(): self
    {
        return new self('The payment quote is internally inconsistent.');
    }

    public static function unsupportedMethod(string $method): self
    {
        return new self("Unsupported payment method: {$method}.");
    }
}
