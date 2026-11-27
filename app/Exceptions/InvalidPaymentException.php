<?php

namespace App\Exceptions;

use DomainException;

class InvalidPaymentException extends DomainException
{
    public static function negativeAmount(): self
    {
        return new self('Valores monetários não podem ser negativos.');
    }

    public static function amountTooLarge(): self
    {
        return new self('O valor é grande demais para ser calculado com segurança.');
    }

    public static function receivedAmountRequired(): self
    {
        return new self('Informe o valor recebido para pagamento em dinheiro.');
    }

    public static function insufficientReceivedAmount(): self
    {
        return new self('O valor recebido é menor que o total a pagar.');
    }

    public static function inconsistentQuote(): self
    {
        return new self('A cotação de pagamento é inconsistente.');
    }

    public static function inconsistentResult(): self
    {
        return new self('O resultado do processamento de pagamento é inconsistente.');
    }

    public static function unsupportedMethod(string $method): self
    {
        return new self("Forma de pagamento não suportada: {$method}.");
    }
}
