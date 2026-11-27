<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderException;
use App\Models\Order;
use App\Models\Product;
use App\Patterns\Decorator\BaconDecorator;
use App\Patterns\Decorator\BaseMenuItem;
use App\Patterns\Decorator\CheeseDecorator;
use App\Patterns\Decorator\EggDecorator;
use App\Patterns\Decorator\Extra;
use App\Patterns\Decorator\ExtraCatalog;
use App\Patterns\Decorator\MenuItem;
use App\Patterns\Decorator\MenuItemDecorator;

class OrderService
{
    /**
     * Maps each catalog extra code to its concrete decorator. Selecting the class is the
     * only place that branches on the extra; price and description come from the composition.
     *
     * @var array<string, class-string<MenuItemDecorator>>
     */
    public const DECORATORS = [
        'bacon' => BaconDecorator::class,
        'cheese' => CheeseDecorator::class,
        'egg' => EggDecorator::class,
    ];

    public function __construct(private readonly ExtraCatalog $catalog) {}

    /**
     * Creates a pending order whose subtotal and description come from the decorator chain.
     *
     * @param  list<mixed>  $extraCodes
     *
     * @throws InvalidOrderException
     */
    public function create(Product $product, array $extraCodes): Order
    {
        if (! $product->active) {
            throw InvalidOrderException::inactiveProduct();
        }

        $extras = $this->selectExtras($product, $extraCodes);

        $item = new BaseMenuItem($product->name, $product->base_price_cents);
        foreach ($extras as $extra) {
            $decorator = self::DECORATORS[$extra->code];
            $item = new $decorator($item, $extra);
        }

        return $this->persist($product, $item, $extras);
    }

    /**
     * @param  list<mixed>  $extraCodes
     * @return list<Extra> selected extras in catalog order
     */
    private function selectExtras(Product $product, array $extraCodes): array
    {
        if (array_filter($extraCodes, fn (mixed $code): bool => ! is_string($code)) !== []) {
            throw InvalidOrderException::invalidExtra('Extras must be identified by code.');
        }
        if (count($extraCodes) !== count(array_unique($extraCodes))) {
            throw InvalidOrderException::invalidExtra('An extra can be selected only once.');
        }

        $allowed = $this->catalog->allowedFor($product->code);
        foreach ($extraCodes as $code) {
            if ($this->catalog->find($code) === null) {
                throw InvalidOrderException::invalidExtra("Unknown extra: {$code}.");
            }
            if (! isset($allowed[$code])) {
                throw InvalidOrderException::invalidExtra("Extra {$code} is not available for this product.");
            }
        }

        return array_values(array_filter($allowed, fn (Extra $extra): bool => in_array($extra->code, $extraCodes, true)));
    }

    /** @param list<Extra> $extras */
    private function persist(Product $product, MenuItem $item, array $extras): Order
    {
        $order = new Order([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'extras_snapshot' => array_map(fn (Extra $extra): array => [
                'code' => $extra->code,
                'name' => $extra->name,
                'price_cents' => $extra->priceCents,
            ], $extras),
            'subtotal_cents' => $item->priceCents(),
        ]);
        $order->status = OrderStatus::Pending;
        $order->save();

        return $order;
    }
}
