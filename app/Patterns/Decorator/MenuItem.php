<?php

namespace App\Patterns\Decorator;

/**
 * Decorator pattern — Component.
 * Common contract of the base item and of every decorator.
 */
interface MenuItem
{
    public function priceCents(): int;

    public function description(): string;
}
