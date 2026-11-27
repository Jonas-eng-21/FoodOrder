<?php

namespace App\Services;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\State\OrderState;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Coordinates lifecycle transitions: it only opens the transaction, re-reads the order, asks the
 * current State for the next state and applies it through Order::transitionTo(). It holds no rules.
 *
 * Concurrency (SQLite): same strategy as PaymentService — BEGIN IMMEDIATE (config/database.php) takes
 * the write lock up front and the order is re-read inside it, so a stale instance never decides.
 */
class OrderLifecycleService
{
    public function __construct(private readonly OrderEventPublisher $events) {}

    /** @throws InvalidOrderTransitionException */
    public function start(Order $order): Order
    {
        return $this->transition($order, fn (OrderState $state): OrderState => $state->start());
    }

    /** @throws InvalidOrderTransitionException */
    public function ready(Order $order): Order
    {
        return $this->transition($order, fn (OrderState $state): OrderState => $state->ready());
    }

    /** @throws InvalidOrderTransitionException */
    public function deliver(Order $order): Order
    {
        return $this->transition($order, fn (OrderState $state): OrderState => $state->deliver());
    }

    /** @throws InvalidOrderTransitionException */
    public function cancel(Order $order): Order
    {
        return $this->transition($order, fn (OrderState $state): OrderState => $state->cancel());
    }

    /** @param Closure(OrderState): OrderState $operation */
    private function transition(Order $order, Closure $operation): Order
    {
        return DB::transaction(function () use ($order, $operation): Order {
            $order = Order::query()->findOrFail($order->getKey());

            $order->transitionTo($operation($order->state()), $this->events);

            return $order;
        });
    }
}
