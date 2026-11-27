<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Patterns\Adapter\CardGatewayAdapter;
use App\Patterns\Adapter\CashPaymentGateway;
use App\Patterns\Adapter\LegacyCardGateway;
use App\Patterns\Adapter\LegacyPixGateway;
use App\Patterns\Adapter\PixGatewayAdapter;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\Strategy\PaymentCalculator;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->order = app(OrderService::class)->create(
            Product::query()->where('code', 'burger')->firstOrFail(),
            ['bacon', 'cheese'],
        );
    }

    private function pay(array $data, ?Order $order = null)
    {
        return $this->post(route('orders.pay', $order ?? $this->order), $data);
    }

    public function test_pending_order_detail_shows_the_payment_form_with_the_simulation_notice(): void
    {
        $this->get(route('orders.show', $this->order))
            ->assertOk()
            ->assertSee('Pagamento simulado — nenhuma cobrança real será realizada.')
            ->assertSee('name="method"', false)
            ->assertSee('value="pix"', false)
            ->assertSee('value="card"', false)
            ->assertSee('value="cash"', false)
            ->assertSee('5% de desconto')
            ->assertSee('name="received_amount"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('R$ 27,00')
            ->assertDontSee('CVV')
            ->assertDontSee('número do cartão', false)
            ->assertDontSee('chave PIX');
    }

    public function test_pix_payment_marks_the_order_paid_and_shows_the_payment_data(): void
    {
        $this->pay(['method' => 'pix'])->assertRedirect(route('orders.show', $this->order));

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(2565, $payment->total_cents);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);

        $this->get(route('orders.show', $this->order))
            ->assertOk()
            ->assertSee('Pago')
            ->assertSee('R$ 25,65')
            ->assertSee('R$ 1,35')
            ->assertSee($payment->simulated_reference)
            ->assertSee('exclusivamente simulado')
            ->assertDontSee('name="method"', false);
    }

    public function test_card_payment_charges_the_full_amount(): void
    {
        $this->pay(['method' => 'card'])->assertRedirect();

        $this->assertSame(2700, Payment::query()->firstOrFail()->total_cents);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
    }

    /** @return array<string, array{string, int}> */
    public static function cashAmounts(): array
    {
        return [
            'dot' => ['30.00', 300],
            'comma' => ['30,00', 300],
            'exact' => ['27', 0],
            'one decimal' => ['27.5', 50],
        ];
    }

    #[DataProvider('cashAmounts')]
    public function test_cash_payment_calculates_change(string $received, int $change): void
    {
        $this->pay(['method' => 'cash', 'received_amount' => $received])->assertRedirect();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(2700, $payment->total_cents);
        $this->assertSame($change, $payment->change_cents);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPayments(): array
    {
        return [
            'insufficient cash' => [['method' => 'cash', 'received_amount' => '26.99'], 'received_amount|error'],
            'missing cash amount' => [['method' => 'cash'], 'received_amount'],
            'negative amount' => [['method' => 'cash', 'received_amount' => '-1'], 'received_amount'],
            'three decimals' => [['method' => 'cash', 'received_amount' => '30.999'], 'received_amount'],
            'scientific notation' => [['method' => 'cash', 'received_amount' => '1e3'], 'received_amount'],
            'letters' => [['method' => 'cash', 'received_amount' => 'abc'], 'received_amount'],
            'array amount' => [['method' => 'cash', 'received_amount' => ['30']], 'received_amount'],
            'unknown method' => [['method' => 'boleto'], 'method'],
            'missing method' => [[], 'method'],
            'array method' => [['method' => ['pix']], 'method'],
        ];
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('invalidPayments')]
    public function test_invalid_requests_are_rejected_without_payment(array $data, string $errorKey): void
    {
        $response = $this->pay($data);

        str_ends_with($errorKey, '|error')
            ? $response->assertSessionHas('error')
            : $response->assertSessionHasErrors($errorKey);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
    }

    public function test_forged_totals_discounts_change_and_status_are_ignored(): void
    {
        $this->pay([
            'method' => 'pix',
            'total_cents' => 1,
            'subtotal_cents' => 1,
            'discount_cents' => 2000,
            'change_cents' => 999,
            'received_cents' => 1,
            'status' => 'delivered',
            'order_id' => 999,
            'simulated_reference' => 'HACK',
        ])->assertRedirect();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame([$this->order->id, 2565, null, null], [$payment->order_id, $payment->total_cents, $payment->received_cents, $payment->change_cents]);
        $this->assertStringStartsWith('SIMULATED-PIX-', $payment->simulated_reference);
        $this->assertSame(2700, $this->order->fresh()->subtotal_cents);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
    }

    public function test_paying_twice_is_rejected_and_creates_a_single_payment(): void
    {
        $this->pay(['method' => 'pix'])->assertRedirect();

        $this->pay(['method' => 'card'])
            ->assertRedirect(route('orders.show', $this->order))
            ->assertSessionHas('error');

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(2565, Payment::query()->firstOrFail()->total_cents);
    }

    public function test_gateway_rejection_shows_a_message_and_a_new_attempt_succeeds(): void
    {
        $this->app->bind(PaymentService::class, fn () => new PaymentService(app(PaymentCalculator::class), [
            'pix' => new PixGatewayAdapter(new LegacyPixGateway(reject: true)),
            'card' => new CardGatewayAdapter(new LegacyCardGateway(reject: true)),
            'cash' => new CashPaymentGateway,
        ], app(OrderEventPublisher::class)));

        $this->pay(['method' => 'pix'])->assertSessionHas('error');
        $this->pay(['method' => 'card'])->assertSessionHas('error');
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);

        $this->pay(['method' => 'cash', 'received_amount' => '27'])->assertRedirect();
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_unknown_order_returns_404(): void
    {
        $this->post('/orders/9999/pay', ['method' => 'pix'])->assertNotFound();
    }

    public function test_payment_cannot_be_triggered_by_get(): void
    {
        $this->get(route('orders.pay', $this->order))->assertStatus(405);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_paid_order_keeps_its_payment_data_after_catalog_changes(): void
    {
        $this->pay(['method' => 'pix']);
        Product::query()->where('code', 'burger')->update(['name' => 'Renomeado', 'base_price_cents' => 9999]);

        $this->get(route('orders.show', $this->order))
            ->assertOk()
            ->assertSee('R$ 25,65')
            ->assertSee('R$ 27,00')
            ->assertDontSee('Renomeado');
    }
}
