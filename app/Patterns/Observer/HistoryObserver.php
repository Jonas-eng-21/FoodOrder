<?php

namespace App\Patterns\Observer;

use App\Models\OrderHistory;

/** Observer pattern — Concrete Observer: records the transition (one row per valid transition). */
final class HistoryObserver implements OrderObserver
{
    public function update(OrderStateChanged $event): void
    {
        OrderHistory::query()->create([
            'order_id' => $event->orderId,
            'previous_status' => $event->previousStatus,
            'new_status' => $event->newStatus,
        ]);
    }
}
