<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderException;
use App\Models\Order;
use App\Models\Product;
use App\Patterns\Decorator\ExtraCatalog;
use App\Services\OrderService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function product(string $code): Product
    {
        return Product::query()->where('code', $code)->firstOrFail();
    }

    /** @return array<string, array{string, list<string>, int}> */
    public static function compositions(): array
    {
        return [
            'burger plain' => ['burger', [], 2000],
            'burger bacon' => ['burger', ['bacon'], 2400],
            'burger bacon cheese' => ['burger', ['bacon', 'cheese'], 2700],
            'burger all' => ['burger', ['bacon', 'cheese', 'egg'], 2900],
            'hotdog cheese' => ['hotdog', ['cheese'], 1800],
            'hotdog all' => ['hotdog', ['bacon', 'cheese', 'egg'], 2400],
            'fries plain' => ['fries', [], 1000],
        ];
    }

    /** @param list<string> $extras */
    #[DataProvider('compositions')]
    public function test_subtotal_is_calculated_through_the_decorators(string $code, array $extras, int $subtotal): void
    {
        $order = app(OrderService::class)->create($this->product($code), $extras);

        $this->assertSame($subtotal, $order->fresh()->subtotal_cents);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_order_stores_product_snapshot_and_extras_snapshot(): void
    {
        $order = app(OrderService::class)->create($this->product('burger'), ['egg', 'bacon']);

        $order = $order->fresh();
        $this->assertSame('Hambúrguer', $order->product_name_snapshot);
        $this->assertSame($this->product('burger')->id, $order->product_id);
        $this->assertSame([
            ['code' => 'bacon', 'name' => 'Bacon', 'price_cents' => 400],
            ['code' => 'egg', 'name' => 'Ovo', 'price_cents' => 200],
        ], $order->extras_snapshot);
        $this->assertSame('Hambúrguer + Bacon + Ovo', $order->description);
    }

    public function test_snapshot_is_stable_after_catalog_changes(): void
    {
        $product = $this->product('burger');
        $order = app(OrderService::class)->create($product, ['bacon']);

        $product->update(['name' => 'Novo nome', 'base_price_cents' => 9999]);
        config(['foodorder.extras.bacon.price_cents' => 999, 'foodorder.extras.bacon.name' => 'Outro']);

        $order = Order::query()->findOrFail($order->id);
        $this->assertSame(2400, $order->subtotal_cents);
        $this->assertSame('Hambúrguer', $order->product_name_snapshot);
        $this->assertSame('Hambúrguer + Bacon', $order->description);
    }

    /** @param list<string> $extras */
    #[DataProvider('invalidSelections')]
    public function test_invalid_selections_are_rejected_without_persisting(string $code, array $extras): void
    {
        try {
            app(OrderService::class)->create($this->product($code), $extras);
            $this->fail('Expected InvalidOrderException.');
        } catch (InvalidOrderException) {
            $this->assertSame(0, Order::query()->count());
        }
    }

    /** @return array<string, array{string, list<string>}> */
    public static function invalidSelections(): array
    {
        return [
            'unknown extra' => ['burger', ['ketchup']],
            'duplicate extra' => ['burger', ['bacon', 'bacon']],
            'incompatible extra' => ['fries', ['bacon']],
        ];
    }

    public function test_inactive_products_are_rejected(): void
    {
        $product = $this->product('burger');
        $product->update(['active' => false]);

        $this->expectException(InvalidOrderException::class);

        app(OrderService::class)->create($product, []);
    }

    public function test_every_catalog_extra_has_a_decorator(): void
    {
        $codes = (new ExtraCatalog(config('foodorder.extras')))->codes();

        $this->assertEqualsCanonicalizing($codes, array_keys(OrderService::DECORATORS));
    }
}
