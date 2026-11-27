<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\InvalidPaymentException;
use App\Exceptions\PaymentRejectedException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Patterns\Adapter\CardGatewayAdapter;
use App\Patterns\Adapter\CashPaymentGateway;
use App\Patterns\Adapter\LegacyCardGateway;
use App\Patterns\Adapter\LegacyPixGateway;
use App\Patterns\Adapter\PaymentGateway;
use App\Patterns\Adapter\PaymentResult;
use App\Patterns\Adapter\PixGatewayAdapter;
use App\Patterns\Strategy\PaymentCalculator;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $this->seed(CatalogSeeder::class);

        return app(OrderService::class)->create(
            Product::query()->where('code', 'burger')->firstOrFail(),
            ['bacon', 'cheese'],
        );
    }

    /** @param array<string, PaymentGateway> $gateways */
    private function service(array $gateways): PaymentService
    {
        return new PaymentService(app(PaymentCalculator::class), $gateways);
    }

    /** @return array<string, PaymentGateway> */
    private function realGateways(): array
    {
        return [
            'pix' => new PixGatewayAdapter(new LegacyPixGateway),
            'card' => new CardGatewayAdapter(new LegacyCardGateway),
            'cash' => new CashPaymentGateway,
        ];
    }

    private function countingGateway(?PaymentResult $result = null): object
    {
        return new class($result) implements PaymentGateway
        {
            /** @var list<int> */
            public array $charged = [];

            public function __construct(private readonly ?PaymentResult $result) {}

            public function charge(int $totalCents): PaymentResult
            {
                $this->charged[] = $totalCents;

                return $this->result ?? PaymentResult::approved('SIMULATED-TEST-1');
            }
        };
    }

    public function test_pix_payment_uses_the_strategy_total_and_marks_the_order_paid(): void
    {
        $order = $this->order();
        $gateway = $this->countingGateway();

        $payment = $this->service(['pix' => $gateway] + $this->realGateways())->pay($order, PaymentMethod::Pix);

        $this->assertSame([2565], $gateway->charged);
        $this->assertSame(PaymentMethod::Pix, $payment->method);
        $this->assertSame(2565, $payment->total_cents);
        $this->assertNull($payment->received_cents);
        $this->assertNull($payment->change_cents);
        $this->assertSame('SIMULATED-TEST-1', $payment->simulated_reference);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(2700, $order->fresh()->subtotal_cents);
    }

    public function test_card_payment_charges_the_full_subtotal(): void
    {
        $order = $this->order();

        $payment = $this->service($this->realGateways())->pay($order, PaymentMethod::Card);

        $this->assertSame(2700, $payment->total_cents);
        $this->assertStringStartsWith('SIMULATED-CARD-', $payment->simulated_reference);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_cash_payment_records_received_and_change(): void
    {
        $order = $this->order();

        $payment = $this->service($this->realGateways())->pay($order, PaymentMethod::Cash, 3000);

        $this->assertSame([2700, 3000, 300], [$payment->total_cents, $payment->received_cents, $payment->change_cents]);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_cash_exactly_equal_to_the_total_gives_zero_change(): void
    {
        $payment = $this->service($this->realGateways())->pay($this->order(), PaymentMethod::Cash, 2700);

        $this->assertSame(0, $payment->change_cents);
    }

    public function test_payment_preserves_the_order_subtotal_and_snapshots(): void
    {
        $order = $this->order();
        $before = $order->fresh()->only(['subtotal_cents', 'product_name_snapshot', 'extras_snapshot']);

        $this->service($this->realGateways())->pay($order, PaymentMethod::Pix);

        $this->assertSame($before, $order->fresh()->only(['subtotal_cents', 'product_name_snapshot', 'extras_snapshot']));
    }

    /** @return array<string, array{PaymentMethod, int|null}> */
    public static function invalidCash(): array
    {
        return [
            'insufficient' => [PaymentMethod::Cash, 2699],
            'missing' => [PaymentMethod::Cash, null],
            'negative' => [PaymentMethod::Cash, -100],
        ];
    }

    #[DataProvider('invalidCash')]
    public function test_invalid_cash_keeps_the_order_pending_and_never_calls_the_gateway(PaymentMethod $method, ?int $received): void
    {
        $order = $this->order();
        $cash = $this->countingGateway();

        try {
            $this->service(['cash' => $cash] + $this->realGateways())->pay($order, $method, $received);
            $this->fail('Expected InvalidPaymentException.');
        } catch (InvalidPaymentException) {
            $this->assertSame([], $cash->charged);
            $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
            $this->assertSame(0, Payment::query()->count());
        }
    }

    public function test_gateway_rejection_keeps_the_order_pending_and_allows_a_retry(): void
    {
        $order = $this->order();

        foreach ([
            ['pix' => new PixGatewayAdapter(new LegacyPixGateway(reject: true))],
            ['card' => new CardGatewayAdapter(new LegacyCardGateway(reject: true))],
        ] as $rejecting) {
            $method = array_key_first($rejecting) === 'pix' ? PaymentMethod::Pix : PaymentMethod::Card;

            try {
                $this->service($rejecting + $this->realGateways())->pay($order, $method);
                $this->fail('Expected PaymentRejectedException.');
            } catch (PaymentRejectedException) {
                $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
                $this->assertSame(0, Payment::query()->count());
            }
        }

        $payment = $this->service($this->realGateways())->pay($order->fresh(), PaymentMethod::Pix);

        $this->assertSame(2565, $payment->total_cents);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_gateway_exception_keeps_the_order_pending(): void
    {
        $order = $this->order();
        $failing = new class implements PaymentGateway
        {
            public function charge(int $totalCents): PaymentResult
            {
                throw new RuntimeException('network down');
            }
        };

        try {
            $this->service(['pix' => $failing] + $this->realGateways())->pay($order, PaymentMethod::Pix);
            $this->fail('Expected PaymentRejectedException.');
        } catch (PaymentRejectedException) {
            $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
            $this->assertSame(0, Payment::query()->count());
        }
    }

    public function test_a_paid_order_cannot_be_paid_again_and_the_gateway_is_not_called(): void
    {
        $order = $this->order();
        $gateway = $this->countingGateway();
        $service = $this->service(['pix' => $gateway] + $this->realGateways());

        $service->pay($order, PaymentMethod::Pix);

        try {
            $service->pay($order, PaymentMethod::Pix);
            $this->fail('Expected InvalidOrderTransitionException.');
        } catch (InvalidOrderTransitionException) {
            $this->assertCount(1, $gateway->charged);
            $this->assertSame(1, Payment::query()->count());
        }

        // A stale in-memory copy that still says "pending" is also rejected: the state is re-read.
        $stale = new Order;
        $stale->forceFill(['id' => $order->id, 'status' => OrderStatus::Pending]);
        $this->expectException(InvalidOrderTransitionException::class);
        $service->pay($stale, PaymentMethod::Card);
    }

    public function test_payments_order_id_is_unique_in_the_database(): void
    {
        $order = $this->order();
        $attributes = ['order_id' => $order->id, 'method' => 'pix', 'total_cents' => 2565, 'simulated_reference' => 'SIMULATED-X'];

        Payment::query()->create($attributes);

        $this->expectException(QueryException::class);

        Payment::query()->create($attributes);
    }

    public function test_failure_after_the_payment_insert_rolls_everything_back(): void
    {
        $order = $this->order();
        Order::saving(function (): void {
            throw new RuntimeException('boom');
        });

        try {
            $this->service($this->realGateways())->pay($order, PaymentMethod::Pix);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        Order::flushEventListeners();
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_failure_inserting_the_payment_leaves_the_order_pending(): void
    {
        $order = $this->order();
        Payment::query()->create(['order_id' => $order->id, 'method' => 'cash', 'total_cents' => 1, 'simulated_reference' => 'PRE-EXISTING']);

        try {
            $this->service($this->realGateways())->pay($order, PaymentMethod::Pix);
            $this->fail('Expected QueryException.');
        } catch (QueryException) {
            $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
            $this->assertSame(1, Payment::query()->count());
        }
    }

    public function test_catalog_changes_do_not_alter_the_payment_or_the_subtotal(): void
    {
        $order = $this->order();
        $this->service($this->realGateways())->pay($order, PaymentMethod::Pix);

        Product::query()->where('code', 'burger')->update(['base_price_cents' => 9999]);
        config(['foodorder.extras.bacon.price_cents' => 999]);

        $this->assertSame(2565, Payment::query()->firstOrFail()->total_cents);
        $this->assertSame(2700, $order->fresh()->subtotal_cents);
    }
}
