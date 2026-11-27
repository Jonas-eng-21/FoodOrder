<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;

/**
 * State pattern — State. Each concrete state decides which operations are allowed and which state follows.
 * Every operation is pure: it returns the next state (or throws) and changes nothing; persistence and
 * transactions belong to the services, and Order::transitionTo() applies the result.
 */
interface OrderState
{
    public function status(): OrderStatus;

    /** @throws InvalidOrderTransitionException when payment is not allowed (called after an approved payment) */
    public function pay(): OrderState;

    /** @throws InvalidOrderTransitionException */
    public function start(): OrderState;

    /** @throws InvalidOrderTransitionException */
    public function ready(): OrderState;

    /** @throws InvalidOrderTransitionException */
    public function deliver(): OrderState;

    /** @throws InvalidOrderTransitionException */
    public function cancel(): OrderState;

    public function canPay(): bool;

    public function canStart(): bool;

    public function canReady(): bool;

    public function canDeliver(): bool;

    public function canCancel(): bool;
}
