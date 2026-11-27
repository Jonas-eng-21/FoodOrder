<?php

namespace App\Patterns\Observer;

use App\Enums\OrderStatus;

/**
 * Observer pattern — event. A fact: this order moved from one status to another.
 * It carries data only; it persists nothing and knows no observer.
 */
final readonly class OrderStateChanged
{
    public function __construct(
        public int $orderId,
        public OrderStatus $previousStatus,
        public OrderStatus $newStatus,
    ) {}

    /** Deterministic identifier of the transition, e.g. "pending_to_paid". */
    public function eventKey(): string
    {
        return "{$this->previousStatus->value}_to_{$this->newStatus->value}";
    }
}
