<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Notification;
use App\Models\OrderHistory;
use App\Models\Payment;
use App\Patterns\Adapter\CardGatewayAdapter;
use App\Patterns\Adapter\CashPaymentGateway;
use App\Patterns\Adapter\LegacyCardGateway;
use App\Patterns\Adapter\LegacyPixGateway;
use App\Patterns\Adapter\PixGatewayAdapter;
use App\Patterns\Observer\HistoryObserver;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\Observer\OrderObserver;
use App\Patterns\Observer\OrderStateChanged;
use App\Patterns\Strategy\PaymentCalculator;
use App\Services\OrderLifecycleService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderObserverRollbackTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
    }

    private function failingObserver(): OrderObserver
    {
        return new class implements OrderObserver
        {
            public function update(OrderStateChanged $event): void
            {
                throw new RuntimeException('observer failure');
            }
        };
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'payments' => Payment::query()->count(),
            'histories' => OrderHistory::query()->count(),
            'notifications' => Notification::query()->count(),
        ];
    }

    public function test_a_failure_after_the_other_observers_persisted_rolls_back_the_payment(): void
    {
        $order = $this->pendingOrder();
        // Registered last: history, customer and kitchen observers have already written when it throws.
        app(OrderEventPublisher::class)->subscribe($this->failingObserver());

        try {
            $this->payThroughService($order);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('observer failure', $e->getMessage());
        }

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(['payments' => 0, 'histories' => 0, 'notifications' => 0], $this->counts());
    }

    public function test_a_failure_in_a_lifecycle_transition_keeps_state_and_previous_records(): void
    {
        $order = $this->pendingOrder();
        $this->payThroughService($order);
        app(OrderLifecycleService::class)->start($order);
        $before = $this->counts();
        $this->assertSame(['payments' => 1, 'histories' => 2, 'notifications' => 3], $before);

        app(OrderEventPublisher::class)->subscribe($this->failingObserver());

        try {
            app(OrderLifecycleService::class)->ready($order);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
        $this->assertSame($before, $this->counts());
        $this->assertSame(['paid', 'preparing'], $order->fresh()->histories->map(fn ($h): string => $h->new_status->value)->all());
    }

    public function test_a_failure_in_the_first_observer_writes_nothing(): void
    {
        $order = $this->pendingOrder();
        $publisher = new OrderEventPublisher;
        $publisher->subscribe($this->failingObserver());
        $publisher->subscribe(app(HistoryObserver::class));
        $lifecycle = new OrderLifecycleService($publisher);

        try {
            $lifecycle->cancel($order);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(['payments' => 0, 'histories' => 0, 'notifications' => 0], $this->counts());
    }

    public function test_the_operation_can_be_retried_after_a_rolled_back_failure(): void
    {
        $order = $this->pendingOrder();
        $publisher = app(OrderEventPublisher::class);
        $failing = $this->failingObserver();
        $publisher->subscribe($failing);

        try {
            $this->payThroughService($order);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
            $publisher->unsubscribe($failing);
        }

        $this->payThroughService($order);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(['payments' => 1, 'histories' => 1, 'notifications' => 2], $this->counts());
    }

    public function test_the_payment_service_uses_the_shared_publisher(): void
    {
        $order = $this->pendingOrder();
        $service = new PaymentService(app(PaymentCalculator::class), [
            'pix' => new PixGatewayAdapter(new LegacyPixGateway),
            'card' => new CardGatewayAdapter(new LegacyCardGateway),
            'cash' => new CashPaymentGateway,
        ], app(OrderEventPublisher::class));

        $service->pay($order, PaymentMethod::Card);

        $this->assertSame(['payments' => 1, 'histories' => 1, 'notifications' => 2], $this->counts());
    }
}
