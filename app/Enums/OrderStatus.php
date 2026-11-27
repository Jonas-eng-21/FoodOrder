<?php

namespace App\Enums;

/**
 * Persisted order status identifiers. This enum only names the persisted values; the allowed
 * operations and transitions live in the State classes (app/Patterns/State).
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando pagamento',
            self::Paid => 'Pago',
            self::Preparing => 'Em preparação',
            self::Ready => 'Pronto',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
        };
    }
}
