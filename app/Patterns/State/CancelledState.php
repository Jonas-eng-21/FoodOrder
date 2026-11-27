<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;

/** State pattern — Concrete State (terminal): every operation is rejected by the defaults. */
final class CancelledState extends AbstractOrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::Cancelled;
    }
}
