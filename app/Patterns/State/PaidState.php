<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;

/** State pattern — Concrete State: payment approved. Allows start (→ preparing). */
final class PaidState extends AbstractOrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::Paid;
    }

    public function start(): OrderState
    {
        return new PreparingState;
    }
}
