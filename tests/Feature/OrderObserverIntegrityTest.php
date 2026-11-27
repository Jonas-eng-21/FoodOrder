<?php

namespace Tests\Feature;

use App\Enums\NotificationAudience;
use App\Enums\OrderStatus;
use App\Models\Notification;
use App\Models\OrderHistory;
use App\Patterns\Observer\CustomerNotificationObserver;
use App\Patterns\Observer\HistoryObserver;
use App\Patterns\Observer\KitchenNotificationObserver;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\Observer\OrderObserver;
use App\Patterns\Observer\OrderStateChanged;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderObserverIntegrityTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
    }

    public function test_the_container_wires_the_three_observers_once_into_a_shared_publisher(): void
    {
        $publisher = app(OrderEventPublisher::class);

        $this->assertSame($publisher, app(OrderEventPublisher::class));
        $this->assertSame(
            [HistoryObserver::class, CustomerNotificationObserver::class, KitchenNotificationObserver::class],
            array_map(fn (OrderObserver $observer): string => $observer::class, $publisher->observers()),
        );
    }

    public function test_the_concrete_observers_implement_the_common_contract(): void
    {
        foreach ([HistoryObserver::class, CustomerNotificationObserver::class, KitchenNotificationObserver::class] as $class) {
            $this->assertInstanceOf(OrderObserver::class, new $class);
        }
    }

    public function test_observers_persist_only_their_own_records_and_do_not_touch_the_order(): void
    {
        $order = $this->orderForcedTo(OrderStatus::Pending);
        $before = $order->fresh()->getAttributes();
        $event = new OrderStateChanged($order->id, OrderStatus::Pending, OrderStatus::Paid);

        (new HistoryObserver)->update($event);
        $this->assertSame([1, 0], [OrderHistory::query()->count(), Notification::query()->count()]);

        (new CustomerNotificationObserver)->update($event);
        $this->assertSame([1, 1], [OrderHistory::query()->count(), Notification::query()->count()]);

        (new KitchenNotificationObserver)->update($event);
        $this->assertSame([1, 2], [OrderHistory::query()->count(), Notification::query()->count()]);
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_the_kitchen_observer_ignores_events_it_does_not_care_about(): void
    {
        $order = $this->pendingOrder();
        $observer = new KitchenNotificationObserver;

        foreach ([[OrderStatus::Paid, OrderStatus::Preparing], [OrderStatus::Ready, OrderStatus::Delivered], [OrderStatus::Pending, OrderStatus::Cancelled]] as [$from, $to]) {
            $observer->update(new OrderStateChanged($order->id, $from, $to));
        }
        $this->assertSame(0, Notification::query()->count());

        $observer->update(new OrderStateChanged($order->id, OrderStatus::Pending, OrderStatus::Paid));
        $observer->update(new OrderStateChanged($order->id, OrderStatus::Preparing, OrderStatus::Ready));

        $this->assertSame(2, Notification::query()->where('audience', NotificationAudience::Kitchen)->count());
    }

    public function test_the_customer_observer_notifies_every_valid_transition(): void
    {
        $order = $this->pendingOrder();
        $observer = new CustomerNotificationObserver;
        $transitions = [
            [OrderStatus::Pending, OrderStatus::Paid, 'Pagamento do pedido confirmado.'],
            [OrderStatus::Paid, OrderStatus::Preparing, 'Seu pedido está em preparação.'],
            [OrderStatus::Preparing, OrderStatus::Ready, 'Seu pedido está pronto para entrega.'],
            [OrderStatus::Ready, OrderStatus::Delivered, 'Seu pedido foi entregue.'],
            [OrderStatus::Pending, OrderStatus::Cancelled, 'Seu pedido foi cancelado.'],
        ];

        foreach ($transitions as [$from, $to, $message]) {
            $observer->update(new OrderStateChanged($order->id, $from, $to));
        }

        $this->assertSame(5, Notification::query()->where('audience', NotificationAudience::Customer)->count());
        $this->assertSame(
            array_column($transitions, 2),
            Notification::query()->orderBy('id')->pluck('message')->all(),
        );
    }

    public function test_history_has_a_unique_constraint_per_order_and_new_status(): void
    {
        $order = $this->pendingOrder();
        $attributes = ['order_id' => $order->id, 'previous_status' => 'pending', 'new_status' => 'paid'];
        OrderHistory::query()->create($attributes);

        $this->expectException(QueryException::class);

        OrderHistory::query()->create($attributes);
    }

    public function test_notifications_have_a_unique_constraint_per_order_event_and_audience(): void
    {
        $order = $this->pendingOrder();
        $attributes = ['order_id' => $order->id, 'event_key' => 'pending_to_paid', 'audience' => 'customer', 'message' => 'x'];
        Notification::query()->create($attributes);
        Notification::query()->create(['audience' => 'kitchen'] + $attributes);

        $this->assertSame(2, Notification::query()->count());
        $this->expectException(QueryException::class);

        Notification::query()->create($attributes);
    }

    public function test_foreign_keys_reject_records_for_unknown_orders(): void
    {
        $this->assertSame(1, (int) DB::selectOne('pragma foreign_keys')->foreign_keys);

        foreach ([
            fn () => OrderHistory::query()->create(['order_id' => 9999, 'previous_status' => 'pending', 'new_status' => 'paid']),
            fn () => Notification::query()->create(['order_id' => 9999, 'event_key' => 'k', 'audience' => 'customer', 'message' => 'm']),
        ] as $insert) {
            try {
                $insert();
                $this->fail('Expected QueryException.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_relationships_and_casts_work_after_reloading(): void
    {
        $order = $this->pendingOrder();
        $this->payThroughService($order);

        $order = $order->fresh();
        $history = $order->histories->firstOrFail();
        $notification = $order->notifications->firstOrFail();

        $this->assertSame($order->id, $history->order->id);
        $this->assertSame($order->id, $notification->order->id);
        $this->assertSame(OrderStatus::Pending, $history->previous_status);
        $this->assertSame(NotificationAudience::Customer, $notification->audience);
        $this->assertSame(2700, $order->subtotal_cents);
        $this->assertSame(0, DB::table('order_histories')->whereNotIn('order_id', DB::table('orders')->select('id'))->count());
    }

    public function test_records_are_not_updated_by_the_application(): void
    {
        $order = $this->pendingOrder();
        $this->payThroughService($order);

        $this->assertNull(OrderHistory::query()->firstOrFail()->updated_at);
        $this->assertFalse(\Schema::hasColumn('order_histories', 'updated_at'));
        $this->assertFalse(\Schema::hasColumn('notifications', 'updated_at'));
    }
}
