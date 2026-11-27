<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Patterns\State\CancelledState;
use App\Patterns\State\DeliveredState;
use App\Patterns\State\OrderState;
use App\Patterns\State\PaidState;
use App\Patterns\State\PendingState;
use App\Patterns\State\PreparingState;
use App\Patterns\State\ReadyState;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderStateRehydrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{OrderStatus, class-string<OrderState>}> */
    public static function states(): array
    {
        return [
            'pending' => [OrderStatus::Pending, PendingState::class],
            'paid' => [OrderStatus::Paid, PaidState::class],
            'preparing' => [OrderStatus::Preparing, PreparingState::class],
            'ready' => [OrderStatus::Ready, ReadyState::class],
            'delivered' => [OrderStatus::Delivered, DeliveredState::class],
            'cancelled' => [OrderStatus::Cancelled, CancelledState::class],
        ];
    }

    /** @param class-string<OrderState> $stateClass */
    #[DataProvider('states')]
    public function test_a_reloaded_order_rehydrates_the_state_class_of_its_persisted_status(OrderStatus $status, string $stateClass): void
    {
        $this->seed(CatalogSeeder::class);
        $order = new Order([
            'product_id' => Product::query()->firstOrFail()->id,
            'product_name_snapshot' => 'Hambúrguer',
            'extras_snapshot' => [],
            'subtotal_cents' => 2000,
        ]);
        $order->status = $status;
        $order->save();

        $this->assertSame($status->value, \DB::table('orders')->where('id', $order->id)->value('status'));

        $reloaded = Order::query()->findOrFail($order->id);

        $this->assertSame($status, $reloaded->status);
        $this->assertInstanceOf($stateClass, $reloaded->state());
        $this->assertSame($status, $reloaded->state()->status());
    }

    public function test_orders_persisted_before_the_new_states_keep_working(): void
    {
        $this->seed(CatalogSeeder::class);
        \DB::table('orders')->insert([
            'product_id' => 1, 'product_name_snapshot' => 'Hambúrguer', 'extras_snapshot' => '[]',
            'subtotal_cents' => 2000, 'status' => 'paid', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $order = Order::query()->firstOrFail();

        $this->assertInstanceOf(PaidState::class, $order->state());
        $this->assertTrue($order->state()->canStart());
    }
}
