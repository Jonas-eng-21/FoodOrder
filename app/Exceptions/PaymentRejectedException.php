<?php

namespace App\Exceptions;

use DomainException;

/** The simulated gateway rejected the payment or failed while processing it. */
class PaymentRejectedException extends DomainException
{
    public static function because(string $reason): self
    {
        return new self("Pagamento simulado rejeitado: {$reason}");
    }

    public static function gatewayFailure(): self
    {
        return new self('Falha ao processar o pagamento simulado. Tente novamente.');
    }
}
