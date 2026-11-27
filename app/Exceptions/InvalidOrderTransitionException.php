<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use DomainException;

class InvalidOrderTransitionException extends DomainException
{
    public static function cannotPay(OrderStatus $status): self
    {
        return new self("Este pedido não pode receber pagamento no estado '{$status->value}'.");
    }
}
