<?php

namespace App\Patterns\Observer;

/**
 * Observer pattern — Subject (publisher). Keeps the subscribed observers (by instance identity) and
 * notifies them synchronously, in subscription order, through the OrderObserver contract only.
 * Exceptions thrown by an observer propagate to the caller (the transaction owner).
 */
final class OrderEventPublisher
{
    /** @var array<int, OrderObserver> keyed by object id: one entry per instance */
    private array $observers = [];

    public function subscribe(OrderObserver $observer): void
    {
        $this->observers[spl_object_id($observer)] = $observer;
    }

    public function unsubscribe(OrderObserver $observer): void
    {
        unset($this->observers[spl_object_id($observer)]);
    }

    public function notify(OrderStateChanged $event): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($event);
        }
    }

    /** @return list<OrderObserver> */
    public function observers(): array
    {
        return array_values($this->observers);
    }
}
