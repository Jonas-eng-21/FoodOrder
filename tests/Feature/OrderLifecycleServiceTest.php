<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Patterns\Adapter\PaymentGateway;
use App\Patterns\Adapter\PaymentResult;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\Strategy\PaymentCalculator;
use App\Services\OrderLifecycleService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OrderLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->lifecycle = app(OrderLifecycleService::class);
    }

    private function pendingOrder(): Order
    {
        return app(OrderService::class)->create(Product::query()->where('code', 'burger')->firstOrFail(), ['bacon', 'cheese']);
    }

    private function paidOrder(): Order
    {
        $order = $this->pendingOrder();
        app(PaymentService::class)->pay($order, PaymentMethod::Pix);

        return $order->fresh();
    }

    private function orderIn(OrderStatus $status): Order
    {
        $order = $status === OrderStatus::Pending || $status === OrderStatus::Cancelled ? $this->pendingOrder() : $this->paidOrder();
        if (! in_array($status, [OrderStatus::Pending, OrderStatus::Paid], true)) {
            $order->forceFill(['status' => $status])->save();
        }

        return $order->fresh();
    }

    /** @return array<string, mixed> */
    private function commercialData(Order $order): array
    {
        return [
            $order->only(['product_id', 'product_name_snapshot', 'extras_snapshot', 'subtotal_cents']),
            Payment::query()->where('order_id', $order->id)->first()?->only(['method', 'total_cents', 'received_cents', 'change_cents', 'simulated_reference']),
        ];
    }

    public function test_the_full_lifecycle_reaches_delivered_and_preserves_commercial_data(): void
    {
        $order = $this->paidOrder();
        $before = $this->commercialData($order);

        $this->assertSame(OrderStatus::Preparing, $this->lifecycle->start($order)->status);
        $this->assertSame(OrderStatus::Ready, $this->lifecycle->ready($order)->status);
        $this->assertSame(OrderStatus::Delivered, $this->lifecycle->deliver($order)->status);

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Delivered, $fresh->status);
        $this->assertSame($before, $this->commercialData($fresh));
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_pending_order_can_be_cancelled_and_has_no_payment(): void
    {
        $order = $this->pendingOrder();

        $this->assertSame(OrderStatus::Cancelled, $this->lifecycle->cancel($order)->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    /** @return array<string, array{OrderStatus, string}> every invalid lifecycle combination (pay is covered below) */
    public static function invalidOperations(): array
    {
        $valid = ['pending' => ['cancel'], 'paid' => ['start'], 'preparing' => ['ready'], 'ready' => ['deliver']];
        $cases = [];
        foreach (OrderStatus::cases() as $status) {
            foreach (['start', 'ready', 'deliver', 'cancel'] as $operation) {
                if (! in_array($operation, $valid[$status->value] ?? [], true)) {
                    $cases["{$status->value} + {$operation}"] = [$status, $operation];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('invalidOperations')]
    public function test_invalid_operations_are_rejected_without_changing_anything(OrderStatus $status, string $operation): void
    {
        $order = $this->orderIn($status);
        $before = [$order->fresh()->getAttributes(), $this->commercialData($order)];

        try {
            $this->lifecycle->$operation($order);
            $this->fail('Expected InvalidOrderTransitionException.');
        } catch (InvalidOrderTransitionException) {
            $this->assertSame($before, [$order->fresh()->getAttributes(), $this->commercialData($order->fresh())]);
        }
    }

    public function test_a_repeated_transition_is_rejected(): void
    {
        $order = $this->paidOrder();
        $this->lifecycle->start($order);

        $this->expectException(InvalidOrderTransitionException::class);

        $this->lifecycle->start($order);
    }

    public function test_the_persisted_state_wins_over_a_stale_instance(): void
    {
        $stale = $this->pendingOrder();
        $this->lifecycle->cancel(Order::query()->findOrFail($stale->id));

        $this->assertSame(OrderStatus::Pending, $stale->status, 'The in-memory copy is stale on purpose.');

        $this->expectException(InvalidOrderTransitionException::class);

        $this->lifecycle->cancel($stale);
    }

    public function test_a_failure_while_saving_rolls_the_transition_back(): void
    {
        $order = $this->paidOrder();
        Order::saving(function (): void {
            throw new RuntimeException('boom');
        });

        try {
            $this->lifecycle->start($order);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
            Order::flushEventListeners();
            $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        }
    }

    /** @return array<string, array{OrderStatus}> */
    public static function nonPendingStatuses(): array
    {
        return [
            'paid' => [OrderStatus::Paid],
            'preparing' => [OrderStatus::Preparing],
            'ready' => [OrderStatus::Ready],
            'delivered' => [OrderStatus::Delivered],
            'cancelled' => [OrderStatus::Cancelled],
        ];
    }

    #[DataProvider('nonPendingStatuses')]
    public function test_payment_is_rejected_before_the_gateway_in_every_non_pending_state(OrderStatus $status): void
    {
        $order = $this->orderIn($status);
        $paymentsBefore = Payment::query()->count();
        $before = [$order->fresh()->getAttributes(), $this->commercialData($order)];
        $gateway = new class implements PaymentGateway
        {
            public int $calls = 0;

            public function charge(int $totalCents): PaymentResult
            {
                $this->calls++;

                return PaymentResult::approved('SIMULATED-TEST');
            }
        };
        $service = new PaymentService(app(PaymentCalculator::class), ['pix' => $gateway, 'card' => $gateway, 'cash' => $gateway], app(OrderEventPublisher::class));

        try {
            $service->pay($order, PaymentMethod::Pix);
            $this->fail('Expected InvalidOrderTransitionException.');
        } catch (InvalidOrderTransitionException) {
            $this->assertSame(0, $gateway->calls);
            $this->assertSame($paymentsBefore, Payment::query()->count());
            $this->assertSame($before, [$order->fresh()->getAttributes(), $this->commercialData($order->fresh())]);
        }
    }
}
