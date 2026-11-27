<?php

namespace Tests\Feature;

use App\Enums\NotificationAudience;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Payment;
use App\Services\OrderLifecycleService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderObserverMatrixTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
    }

    private function perform(Order $order, string $operation): void
    {
        $operation === 'pay'
            ? app(PaymentService::class)->pay($order, PaymentMethod::Pix)
            : app(OrderLifecycleService::class)->$operation($order);
    }

    /** @return array<string, array{OrderStatus, string, OrderStatus, int, int, int, string, string|null}> */
    public static function matrix(): array
    {
        return [
            'pending → paid' => [OrderStatus::Pending, 'pay', OrderStatus::Paid, 1, 1, 1, 'Pagamento do pedido confirmado.', 'Novo pedido pago aguardando preparação.'],
            'paid → preparing' => [OrderStatus::Paid, 'start', OrderStatus::Preparing, 1, 1, 0, 'Seu pedido está em preparação.', null],
            'preparing → ready' => [OrderStatus::Preparing, 'ready', OrderStatus::Ready, 1, 1, 1, 'Seu pedido está pronto para entrega.', 'Pedido finalizado e pronto para entrega.'],
            'ready → delivered' => [OrderStatus::Ready, 'deliver', OrderStatus::Delivered, 1, 1, 0, 'Seu pedido foi entregue.', null],
            'pending → cancelled' => [OrderStatus::Pending, 'cancel', OrderStatus::Cancelled, 1, 1, 0, 'Seu pedido foi cancelado.', null],
        ];
    }

    #[DataProvider('matrix')]
    public function test_each_valid_transition_persists_the_expected_effects(
        OrderStatus $from,
        string $operation,
        OrderStatus $to,
        int $histories,
        int $customer,
        int $kitchen,
        string $customerMessage,
        ?string $kitchenMessage,
    ): void {
        $order = $this->orderForcedTo($from);
        $this->assertSame(0, OrderHistory::query()->count(), 'Forcing the status must not create records.');

        $this->perform($order, $operation);

        $this->assertSame($to, $order->fresh()->status);
        $this->assertSame($histories, OrderHistory::query()->where('order_id', $order->id)->count());
        $history = OrderHistory::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame([$from, $to], [$history->previous_status, $history->new_status]);
        $this->assertNotNull($history->created_at);

        $eventKey = "{$from->value}_to_{$to->value}";
        $customerRows = Notification::query()->where('order_id', $order->id)->where('audience', NotificationAudience::Customer)->get();
        $kitchenRows = Notification::query()->where('order_id', $order->id)->where('audience', NotificationAudience::Kitchen)->get();

        $this->assertCount($customer, $customerRows);
        $this->assertSame($customerMessage, $customerRows->first()->message);
        $this->assertSame($eventKey, $customerRows->first()->event_key);
        $this->assertCount($kitchen, $kitchenRows);
        if ($kitchenMessage !== null) {
            $this->assertSame($kitchenMessage, $kitchenRows->first()->message);
            $this->assertSame($eventKey, $kitchenRows->first()->event_key);
        }
        $this->assertSame($customer + $kitchen, Notification::query()->count());
    }

    public function test_the_full_cycle_records_four_histories_and_six_notifications_in_order(): void
    {
        $order = $this->pendingOrder();
        $this->assertSame([0, 0], [OrderHistory::query()->count(), Notification::query()->count()], 'Creation is not a transition.');

        $this->perform($order, 'pay');
        foreach (['start', 'ready', 'deliver'] as $operation) {
            $this->perform($order, $operation);
        }

        $order = $order->fresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(1, Payment::query()->count());
        $this->assertCount(4, $order->histories);
        $this->assertSame(
            ['pending→paid', 'paid→preparing', 'preparing→ready', 'ready→delivered'],
            $order->histories->map(fn (OrderHistory $h): string => "{$h->previous_status->value}→{$h->new_status->value}")->all(),
        );
        $this->assertSame(4, $order->notifications->where('audience', NotificationAudience::Customer)->count());
        $this->assertSame(2, $order->notifications->where('audience', NotificationAudience::Kitchen)->count());
        $this->assertCount(6, $order->notifications);
        $this->assertSame(
            ['paid', 'ready'],
            $order->notifications->where('audience', NotificationAudience::Kitchen)->map(fn (Notification $n): string => explode('_to_', $n->event_key)[1])->values()->all(),
        );
    }

    public function test_the_cancellation_flow_records_one_history_and_one_customer_notification(): void
    {
        $order = $this->pendingOrder();

        $this->perform($order, 'cancel');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(1, OrderHistory::query()->count());
        $this->assertSame(1, Notification::query()->where('audience', NotificationAudience::Customer)->count());
        $this->assertSame(0, Notification::query()->where('audience', NotificationAudience::Kitchen)->count());
    }

    public function test_the_complete_flow_through_http_records_the_same_effects(): void
    {
        $this->post('/orders', ['product_id' => 1, 'extras' => ['bacon']])->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame(0, OrderHistory::query()->count());

        $this->post(route('orders.pay', $order), ['method' => 'card'])->assertRedirect();
        foreach (['start', 'ready', 'deliver'] as $action) {
            $this->post(route("orders.{$action}", $order))->assertRedirect();
        }

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame([4, 6], [OrderHistory::query()->count(), Notification::query()->count()]);
    }

    public function test_histories_are_ordered_deterministically_even_with_equal_timestamps(): void
    {
        $order = $this->pendingOrder();
        $this->perform($order, 'pay');
        $this->perform($order, 'start');
        OrderHistory::query()->update(['created_at' => '2026-01-01 10:00:00']);

        $this->assertSame(['paid', 'preparing'], $order->fresh()->histories->map(fn (OrderHistory $h): string => $h->new_status->value)->all());
    }
}
