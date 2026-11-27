<?php

namespace App\Patterns\State;

use App\Exceptions\InvalidOrderTransitionException;

/** Rejects every operation by default; concrete states override only what they allow. */
abstract class AbstractOrderState implements OrderState
{
    public function canPay(): bool
    {
        return false;
    }

    public function pay(): OrderState
    {
        throw InvalidOrderTransitionException::cannotPay($this->status());
    }
}
