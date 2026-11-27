<?php

namespace App\Patterns\State;

use App\Enums\OrderStatus;

/** State pattern — Concrete State: payment approved; a second payment is rejected by the defaults. */
final class PaidState extends AbstractOrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::Paid;
    }
}
