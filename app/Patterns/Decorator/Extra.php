<?php

namespace App\Patterns\Decorator;

use InvalidArgumentException;

/**
 * An order extra as defined by the catalog (config/foodorder.php).
 */
final readonly class Extra
{
    /** @param list<string> $productCodes product codes that accept this extra */
    public function __construct(
        public string $code,
        public string $name,
        public int $priceCents,
        public array $productCodes,
    ) {
        if ($priceCents < 0) {
            throw new InvalidArgumentException('Extra price cannot be negative.');
        }
    }

    public function isAllowedFor(string $productCode): bool
    {
        return in_array($productCode, $this->productCodes, true);
    }
}
