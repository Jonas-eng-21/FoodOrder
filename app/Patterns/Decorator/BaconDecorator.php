<?php

namespace App\Patterns\Decorator;

/**
 * Decorator pattern — Concrete Decorator (Bacon).
 * Price and name come from the extra supplied by the single catalog.
 */
final class BaconDecorator extends MenuItemDecorator
{
    public function priceCents(): int
    {
        return parent::priceCents() + $this->extra->priceCents;
    }

    public function description(): string
    {
        return parent::description().self::SEPARATOR.$this->extra->name;
    }
}
