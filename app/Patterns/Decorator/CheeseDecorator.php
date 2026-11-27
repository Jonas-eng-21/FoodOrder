<?php

namespace App\Patterns\Decorator;

/**
 * Decorator pattern — Concrete Decorator (Cheese).
 * Price and name come from the extra supplied by the single catalog.
 */
final class CheeseDecorator extends MenuItemDecorator
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
