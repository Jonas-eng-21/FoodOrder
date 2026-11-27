<?php

namespace App\Patterns\Observer;

/**
 * Observer pattern — Observer. Reacts to a valid order state change.
 * Implementations run inside the transition's database transaction; an exception aborts it.
 */
interface OrderObserver
{
    public function update(OrderStateChanged $event): void;
}
