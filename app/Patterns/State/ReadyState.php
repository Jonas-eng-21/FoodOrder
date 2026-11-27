<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;

/** State pattern — Concrete State: ready for delivery. Allows deliver (→ delivered). */
final class ReadyState extends AbstractOrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::Ready;
    }

    public function deliver(): OrderState
    {
        return new DeliveredState;
    }
}
