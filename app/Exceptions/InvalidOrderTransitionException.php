<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use DomainException;

class InvalidOrderTransitionException extends DomainException
{
    private const PHRASES = [
        'pay' => 'receber pagamento',
        'start' => 'ter o preparo iniciado',
        'ready' => 'ter o preparo finalizado',
        'deliver' => 'ser entregue',
        'cancel' => 'ser cancelado',
    ];

    public static function notAllowed(string $operation, OrderStatus $status): self
    {
        $phrase = self::PHRASES[$operation] ?? $operation;

        return new self("Este pedido não pode {$phrase} no estado '{$status->label()}'.");
    }

    public static function cannotPay(OrderStatus $status): self
    {
        return self::notAllowed('pay', $status);
    }
}
