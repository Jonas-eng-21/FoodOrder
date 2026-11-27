<?php

namespace App\Patterns\Decorator;

use InvalidArgumentException;

/**
 * Decorator pattern — Concrete Component.
 * The original product: its own name and base price, with no extras.
 */
final class BaseMenuItem implements MenuItem
{
    public function __construct(
        private readonly string $name,
        private readonly int $priceCents,
    ) {
        if ($priceCents < 0) {
            throw new InvalidArgumentException('Menu item price cannot be negative.');
        }
    }

    public function priceCents(): int
    {
        return $this->priceCents;
    }

    public function description(): string
    {
        return $this->name;
    }
}
