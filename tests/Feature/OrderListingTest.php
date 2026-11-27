<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderListingTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
    }

    private function createdAt(Order $order, string $timestamp): void
    {
        DB::table('orders')->where('id', $order->id)->update(['created_at' => $timestamp]);
    }

    public function test_the_empty_state_guides_the_operator_to_the_menu(): void
    {
        $this->get('/orders')
            ->assertOk()
            ->assertSee('Nenhum pedido foi criado até o momento.')
            ->assertSee(route('menu.index'), false)
            ->assertDontSee('<table', false);
    }

    public function test_orders_are_listed_with_snapshot_description_subtotal_state_and_a_detail_link(): void
    {
        $order = $this->pendingOrder();

        $this->get(route('orders.index'))
            ->assertOk()
            ->assertSee('#'.$order->id)
            ->assertSee('Hambúrguer + Bacon + Queijo extra')
            ->assertSee('R$ 27,00')
            ->assertSee('Aguardando pagamento')
            ->assertSee(route('orders.show', $order), false);
    }

    public function test_every_state_is_listed_with_its_label(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->orderForcedTo($status);
        }

        $response = $this->get('/orders')->assertOk();

        foreach (OrderStatus::cases() as $status) {
            $response->assertSee($status->label());
        }
    }

    public function test_orders_are_ordered_by_creation_descending(): void
    {
        $oldest = $this->pendingOrder();
        $middle = $this->pendingOrder();
        $newest = $this->pendingOrder();
        $this->createdAt($oldest, '2026-01-01 10:00:00');
        $this->createdAt($middle, '2026-01-02 10:00:00');
        $this->createdAt($newest, '2026-01-03 10:00:00');

        $this->get('/orders')->assertSeeInOrder([
            route('orders.show', $newest), route('orders.show', $middle), route('orders.show', $oldest),
        ], false);
    }

    public function test_the_id_breaks_ties_between_equal_creation_times_descending(): void
    {
        $first = $this->pendingOrder();
        $second = $this->pendingOrder();
        $third = $this->pendingOrder();
        foreach ([$first, $second, $third] as $order) {
            $this->createdAt($order, '2026-01-01 10:00:00');
        }

        $this->get('/orders')->assertSeeInOrder([
            route('orders.show', $third), route('orders.show', $second), route('orders.show', $first),
        ], false);
    }

    public function test_an_older_order_with_a_larger_id_does_not_beat_creation_time(): void
    {
        $lowId = $this->pendingOrder();
        $highId = $this->pendingOrder();
        $this->createdAt($lowId, '2026-06-01 10:00:00');
        $this->createdAt($highId, '2026-01-01 10:00:00');

        $this->get('/orders')->assertSeeInOrder([route('orders.show', $lowId), route('orders.show', $highId)], false);
    }

    public function test_the_listing_uses_snapshots_and_ignores_catalog_changes(): void
    {
        $order = $this->pendingOrder();
        Product::query()->where('code', 'burger')->update(['name' => 'Renomeado', 'base_price_cents' => 9999]);
        config(['foodorder.extras.bacon.price_cents' => 999]);

        $this->get('/orders')
            ->assertOk()
            ->assertSee('Hambúrguer + Bacon + Queijo extra')
            ->assertSee('R$ 27,00')
            ->assertDontSee('Renomeado');
    }

    public function test_stored_text_is_escaped(): void
    {
        $order = $this->pendingOrder();
        $order->forceFill(['product_name_snapshot' => '<script>alert(1)</script>'])->save();

        $this->get('/orders')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_listing_does_not_run_one_query_per_order(): void
    {
        $this->pendingOrder();
        DB::enableQueryLog();
        $this->get('/orders')->assertOk();
        $queriesWithOneOrder = count(DB::getQueryLog());

        foreach (range(1, 12) as $ignored) {
            $this->pendingOrder();
        }
        DB::flushQueryLog();
        $this->get('/orders')->assertOk();

        $this->assertSame($queriesWithOneOrder, count(DB::getQueryLog()));
    }

    public function test_listing_does_not_change_any_data(): void
    {
        $order = $this->pendingOrder();
        $this->payThroughService($order);
        $counts = [Order::query()->count(), Payment::query()->count(), OrderHistory::query()->count(), Notification::query()->count()];
        $before = $order->fresh()->getAttributes();

        $this->get('/orders')->assertOk();

        $this->assertSame($counts, [Order::query()->count(), Payment::query()->count(), OrderHistory::query()->count(), Notification::query()->count()]);
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_navigation_links_the_menu_and_the_orders_and_marks_the_current_page(): void
    {
        $this->get('/orders')
            ->assertSee(route('menu.index'), false)
            ->assertSee(route('orders.index'), false)
            ->assertSee('aria-current="page"', false);
        $this->get('/')->assertSee(route('orders.index'), false)->assertSee('aria-current="page"', false);
    }
}
