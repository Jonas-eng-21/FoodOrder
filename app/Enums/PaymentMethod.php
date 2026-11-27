<?php

namespace App\Enums;

/** Payment methods accepted by the simulation; the values are the persisted identifiers. */
enum PaymentMethod: string
{
    case Pix = 'pix';
    case Card = 'card';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::Card => 'Cartão',
            self::Cash => 'Dinheiro',
        };
    }
}
