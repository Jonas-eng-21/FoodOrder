<?php

namespace App\Patterns\Observer;

use App\Enums\NotificationAudience;
use App\Enums\OrderStatus;
use App\Models\Notification;
use InvalidArgumentException;

/** Observer pattern — Concrete Observer: an internal notice for the customer on every valid transition. */
final class CustomerNotificationObserver implements OrderObserver
{
    public function update(OrderStateChanged $event): void
    {
        Notification::query()->create([
            'order_id' => $event->orderId,
            'event_key' => $event->eventKey(),
            'audience' => NotificationAudience::Customer,
            'message' => $this->message($event->newStatus),
        ]);
    }

    private function message(OrderStatus $newStatus): string
    {
        return match ($newStatus) {
            OrderStatus::Paid => 'Pagamento do pedido confirmado.',
            OrderStatus::Preparing => 'Seu pedido está em preparação.',
            OrderStatus::Ready => 'Seu pedido está pronto para entrega.',
            OrderStatus::Delivered => 'Seu pedido foi entregue.',
            OrderStatus::Cancelled => 'Seu pedido foi cancelado.',
            OrderStatus::Pending => throw new InvalidArgumentException('No transition leads to the pending status.'),
        };
    }
}
