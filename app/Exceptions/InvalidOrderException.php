<?php

namespace App\Exceptions;

use DomainException;

class InvalidOrderException extends DomainException
{
    public static function inactiveProduct(): self
    {
        return new self('The product is not available for new orders.');
    }

    public static function invalidExtra(string $reason): self
    {
        return new self($reason);
    }
}
