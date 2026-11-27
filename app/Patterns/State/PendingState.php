<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;

/** State pattern — Concrete State: a new order awaiting payment. Allows pay (→ paid) and cancel (→ cancelled). */
final class PendingState extends AbstractOrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::Pending;
    }

    public function pay(): OrderState
    {
        return new PaidState;
    }

    public function cancel(): OrderState
    {
        return new CancelledState;
    }
}
