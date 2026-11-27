<?php

namespace App\Patterns\Decorator;

use Illuminate\Container\Attributes\Config;

/**
 * Read-only access to the extras defined in config/foodorder.php.
 */
final class ExtraCatalog
{
    /** @var array<string, Extra> */
    private array $extras = [];

    /** @param array<string, array{name: string, price_cents: int, products: list<string>}> $extras */
    public function __construct(#[Config('foodorder.extras')] array $extras)
    {
        foreach ($extras as $code => $definition) {
            $this->extras[$code] = new Extra($code, $definition['name'], $definition['price_cents'], $definition['products']);
        }
    }

    /** @return array<string, Extra> */
    public function all(): array
    {
        return $this->extras;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->extras);
    }

    public function find(string $code): ?Extra
    {
        return $this->extras[$code] ?? null;
    }

    /** @return array<string, Extra> extras accepted by the given product code, keyed by extra code */
    public function allowedFor(string $productCode): array
    {
        return array_filter($this->extras, fn (Extra $extra): bool => $extra->isAllowedFor($productCode));
    }
}
