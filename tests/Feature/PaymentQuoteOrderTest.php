<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Patterns\Strategy\PaymentCalculator;
use App\Services\OrderService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentQuoteOrderTest extends TestCase
{
    use RefreshDatabase;

    private function burgerWithBaconAndCheese(): Order
    {
        $this->seed(CatalogSeeder::class);

        return app(OrderService::class)->create(
            Product::query()->where('code', 'burger')->firstOrFail(),
            ['bacon', 'cheese'],
        );
    }

    public function test_quotes_use_the_persisted_subtotal_and_leave_the_order_untouched(): void
    {
        $order = $this->burgerWithBaconAndCheese();
        $before = Order::query()->findOrFail($order->id)->getAttributes();
        $calculator = app(PaymentCalculator::class);

        $pix = $calculator->calculate('pix', $order->subtotal_cents);
        $card = $calculator->calculate('card', $order->subtotal_cents);
        $cash = $calculator->calculate('cash', $order->subtotal_cents, 3000);

        $this->assertSame([2565, 2700, 2700, 300], [$pix->totalCents, $card->totalCents, $cash->totalCents, $cash->changeCents]);
        $this->assertSame($before, Order::query()->findOrFail($order->id)->getAttributes());
        $this->assertSame('pending', $order->fresh()->status->value);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_catalog_changes_do_not_affect_quotes_of_existing_orders(): void
    {
        $order = $this->burgerWithBaconAndCheese();
        $calculator = app(PaymentCalculator::class);
        $first = $calculator->calculate('pix', $order->subtotal_cents);

        Product::query()->where('code', 'burger')->update(['base_price_cents' => 9999]);
        config(['foodorder.extras.bacon.price_cents' => 999]);

        $fresh = Order::query()->findOrFail($order->id);
        $this->assertEquals($first, $calculator->calculate('pix', $fresh->subtotal_cents));
    }

    public function test_quoting_writes_nothing_to_the_database(): void
    {
        $order = $this->burgerWithBaconAndCheese();
        $calculator = app(PaymentCalculator::class);
        DB::enableQueryLog();

        $calculator->calculate('pix', $order->subtotal_cents);
        $calculator->calculate('cash', $order->subtotal_cents, 2700);

        $this->assertSame([], DB::getQueryLog());
    }
}
