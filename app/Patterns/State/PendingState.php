<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;

/** State pattern — Concrete State: a new order awaiting payment. */
final class PendingState extends AbstractOrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::Pending;
    }

    public function canPay(): bool
    {
        return true;
    }

    public function pay(): OrderState
    {
        return new PaidState;
    }
}
