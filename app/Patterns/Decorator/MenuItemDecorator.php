<?php

namespace App\Patterns\Decorator;

/**
 * Decorator pattern — Decorator.
 * Implements MenuItem, wraps another MenuItem and delegates to it by default.
 * Concrete decorators add their own contribution on top of the delegated result.
 */
abstract class MenuItemDecorator implements MenuItem
{
    public const SEPARATOR = ' + ';

    public function __construct(
        protected readonly MenuItem $inner,
        protected readonly Extra $extra,
    ) {}

    public function priceCents(): int
    {
        return $this->inner->priceCents();
    }

    public function description(): string
    {
        return $this->inner->description();
    }
}
