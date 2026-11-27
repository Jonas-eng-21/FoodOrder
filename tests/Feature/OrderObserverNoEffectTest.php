<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\InvalidPaymentException;
use App\Exceptions\PaymentRejectedException;
use App\Models\Notification;
use App\Models\OrderHistory;
use App\Patterns\Adapter\CardGatewayAdapter;
use App\Patterns\Adapter\CashPaymentGateway;
use App\Patterns\Adapter\LegacyCardGateway;
use App\Patterns\Adapter\LegacyPixGateway;
use App\Patterns\Adapter\PaymentGateway;
use App\Patterns\Adapter\PaymentResult;
use App\Patterns\Adapter\PixGatewayAdapter;
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

class OrderObserverNoEffectTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private object $spy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
        $this->spy = new class implements OrderObserver
        {
            /** @var list<OrderStateChanged> */
            public array $events = [];

            public function update(OrderStateChanged $event): void
            {
                $this->events[] = $event;
            }
        };
        app(OrderEventPublisher::class)->subscribe($this->spy);
    }

    private function assertNoEffects(): void
    {
        $this->assertSame([], $this->spy->events);
        $this->assertSame(0, OrderHistory::query()->count());
        $this->assertSame(0, Notification::query()->count());
    }

    private function paymentService(array $gateways): PaymentService
    {
        return new PaymentService(app(PaymentCalculator::class), $gateways, app(OrderEventPublisher::class));
    }

    /** @return array<string, PaymentGateway> */
    private function realGateways(): array
    {
        return ['pix' => new PixGatewayAdapter(new LegacyPixGateway), 'card' => new CardGatewayAdapter(new LegacyCardGateway), 'cash' => new CashPaymentGateway];
    }

    public function test_creating_an_order_publishes_nothing(): void
    {
        $this->pendingOrder();
        $this->post('/orders', ['product_id' => 1])->assertRedirect();

        $this->assertNoEffects();
    }

    public function test_rejected_payments_publish_nothing(): void
    {
        $order = $this->pendingOrder();

        $throwing = new class implements PaymentGateway
        {
            public function charge(int $totalCents): PaymentResult
            {
                throw new RuntimeException('network down');
            }
        };

        foreach ([
            fn () => $this->paymentService(['pix' => new PixGatewayAdapter(new LegacyPixGateway(reject: true))] + $this->realGateways())->pay($order, PaymentMethod::Pix),
            fn () => $this->paymentService(['card' => new CardGatewayAdapter(new LegacyCardGateway(reject: true))] + $this->realGateways())->pay($order, PaymentMethod::Card),
            fn () => $this->paymentService(['pix' => $throwing] + $this->realGateways())->pay($order, PaymentMethod::Pix),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected PaymentRejectedException.');
            } catch (PaymentRejectedException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach ([null, 2699, -100] as $received) {
            try {
                $this->paymentService($this->realGateways())->pay($order, PaymentMethod::Cash, $received);
                $this->fail('Expected InvalidPaymentException.');
            } catch (InvalidPaymentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->post(route('orders.pay', $order), ['method' => 'boleto'])->assertSessionHasErrors('method');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertNoEffects();
    }

    public function test_invalid_transitions_and_repetitions_publish_nothing_new(): void
    {
        $order = $this->pendingOrder();
        $lifecycle = app(OrderLifecycleService::class);

        foreach (['start', 'ready', 'deliver'] as $invalid) {
            try {
                $lifecycle->$invalid($order);
                $this->fail('Expected InvalidOrderTransitionException.');
            } catch (InvalidOrderTransitionException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertNoEffects();

        $lifecycle->cancel($order);
        $this->assertCount(1, $this->spy->events);
        $this->assertSame(1, OrderHistory::query()->count());
        $this->assertSame(1, Notification::query()->count());

        foreach (['start', 'ready', 'deliver', 'cancel'] as $repeat) {
            try {
                $lifecycle->$repeat($order);
                $this->fail('Expected InvalidOrderTransitionException.');
            } catch (InvalidOrderTransitionException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            app(PaymentService::class)->pay($order, PaymentMethod::Pix);
            $this->fail('Expected InvalidOrderTransitionException.');
        } catch (InvalidOrderTransitionException) {
            $this->addToAssertionCount(1);
        }

        $this->assertCount(1, $this->spy->events);
        $this->assertSame([1, 1], [OrderHistory::query()->count(), Notification::query()->count()]);
    }

    public function test_repeated_payment_and_delivery_do_not_duplicate_records(): void
    {
        $order = $this->pendingOrder();
        $this->payThroughService($order);
        $lifecycle = app(OrderLifecycleService::class);
        foreach (['start', 'ready', 'deliver'] as $operation) {
            $lifecycle->$operation($order);
        }
        $counts = [OrderHistory::query()->count(), Notification::query()->count()];

        foreach ([fn () => $this->payThroughService($order), fn () => $lifecycle->deliver($order), fn () => $lifecycle->cancel($order)] as $repeat) {
            try {
                $repeat();
                $this->fail('Expected InvalidOrderTransitionException.');
            } catch (InvalidOrderTransitionException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([4, 6], $counts);
        $this->assertSame($counts, [OrderHistory::query()->count(), Notification::query()->count()]);
    }
}
