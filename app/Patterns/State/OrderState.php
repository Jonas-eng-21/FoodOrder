<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;

/**
 * State pattern — State. Each concrete state decides which operations are allowed and which state follows.
 * Only the payment operation exists so far; start/ready/deliver/cancel arrive with US06.
 */
interface OrderState
{
    public function status(): OrderStatus;

    public function canPay(): bool;

    /**
     * Pure: returns the state that follows an approved payment and changes nothing.
     *
     * @throws InvalidOrderTransitionException when payment is not allowed
     */
    public function pay(): OrderState;
}
