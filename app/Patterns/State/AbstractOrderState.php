<?php

namespace App\Patterns\State;

use App\Exceptions\InvalidOrderTransitionException;
use Closure;

/**
 * Rejects every operation by default; concrete states override only what they allow.
 * The can*() queries are derived from the operations themselves, so the rules live in one place.
 */
abstract class AbstractOrderState implements OrderState
{
    public function pay(): OrderState
    {
        throw InvalidOrderTransitionException::notAllowed('pay', $this->status());
    }

    public function start(): OrderState
    {
        throw InvalidOrderTransitionException::notAllowed('start', $this->status());
    }

    public function ready(): OrderState
    {
        throw InvalidOrderTransitionException::notAllowed('ready', $this->status());
    }

    public function deliver(): OrderState
    {
        throw InvalidOrderTransitionException::notAllowed('deliver', $this->status());
    }

    public function cancel(): OrderState
    {
        throw InvalidOrderTransitionException::notAllowed('cancel', $this->status());
    }

    public function canPay(): bool
    {
        return $this->allows(fn () => $this->pay());
    }

    public function canStart(): bool
    {
        return $this->allows(fn () => $this->start());
    }

    public function canReady(): bool
    {
        return $this->allows(fn () => $this->ready());
    }

    public function canDeliver(): bool
    {
        return $this->allows(fn () => $this->deliver());
    }

    public function canCancel(): bool
    {
        return $this->allows(fn () => $this->cancel());
    }

    private function allows(Closure $operation): bool
    {
        try {
            $operation();

            return true;
        } catch (InvalidOrderTransitionException) {
            return false;
        }
    }
}
