<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;

/** State pattern — Concrete State: the kitchen is preparing the order. Allows ready (→ ready). */
final class PreparingState extends AbstractOrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::Preparing;
    }

    public function ready(): OrderState
    {
        return new ReadyState;
    }
}
