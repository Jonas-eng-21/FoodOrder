<?php

namespace App\Patterns\Observer;

use App\Enums\NotificationAudience;
use App\Enums\OrderStatus;
use App\Models\Notification;

/**
 * Observer pattern — Concrete Observer: an internal notice for the kitchen, only when the order
 * becomes paid or ready. It decides by itself which events interest it; other events are ignored.
 */
final class KitchenNotificationObserver implements OrderObserver
{
    public function update(OrderStateChanged $event): void
    {
        $message = match ($event->newStatus) {
            OrderStatus::Paid => 'Novo pedido pago aguardando preparação.',
            OrderStatus::Ready => 'Pedido finalizado e pronto para entrega.',
            default => null,
        };

        if ($message === null) {
            return;
        }

        Notification::query()->create([
            'order_id' => $event->orderId,
            'event_key' => $event->eventKey(),
            'audience' => NotificationAudience::Kitchen,
            'message' => $message,
        ]);
    }
}
