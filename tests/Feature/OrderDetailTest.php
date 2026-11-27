<?php

namespace Tests\Feature;

use App\Enums\NotificationAudience;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\Observer\OrderObserver;
use App\Patterns\Observer\OrderStateChanged;
use App\Services\OrderLifecycleService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderDetailTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
    }

    private function fullCycle(): Order
    {
        $order = $this->pendingOrder();
        app(PaymentService::class)->pay($order, PaymentMethod::Pix);
        foreach (['start', 'ready', 'deliver'] as $operation) {
            app(OrderLifecycleService::class)->$operation($order);
        }

        return $order->fresh();
    }

    public function test_the_detail_shows_the_four_consolidated_sections(): void
    {
        $this->get(route('orders.show', $this->pendingOrder()))
            ->assertOk()
            ->assertSeeInOrder(['Composição do pedido', 'Pagamento', 'Histórico do pedido', 'Notificações internas']);
    }

    public function test_the_composition_shows_product_extras_and_how_they_add_up_to_the_subtotal(): void
    {
        $order = $this->pendingOrder();

        $this->get(route('orders.show', $order))
            ->assertSeeInOrder(['Hambúrguer', 'R$ 20,00', 'Bacon', 'R$ 4,00', 'Queijo extra', 'R$ 3,00', 'Subtotal', 'R$ 27,00'])
            ->assertSee('Hambúrguer + Bacon + Queijo extra');
    }

    public function test_the_composition_is_stable_after_catalog_changes(): void
    {
        $order = $this->pendingOrder();
        DB::table('products')->where('code', 'burger')->update(['name' => 'Renomeado', 'base_price_cents' => 9999]);
        config(['foodorder.extras.bacon.price_cents' => 999]);

        $this->get(route('orders.show', $order))
            ->assertSeeInOrder(['Hambúrguer', 'R$ 20,00', 'Bacon', 'R$ 4,00'])
            ->assertDontSee('Renomeado')
            ->assertDontSee('R$ 99,99')
            ->assertDontSee('R$ 9,99');
    }

    public function test_a_pix_payment_shows_subtotal_discount_and_total_paid_separately(): void
    {
        $order = $this->pendingOrder();
        app(PaymentService::class)->pay($order, PaymentMethod::Pix);
        $payment = $order->fresh()->payment;

        $this->get(route('orders.show', $order))
            ->assertSeeInOrder(['Subtotal original', 'R$ 27,00', 'Desconto', 'R$ 1,35', 'Total pago', 'R$ 25,65'])
            ->assertSee('PIX')
            ->assertSee($payment->simulated_reference)
            ->assertSee('exclusivamente simulado')
            ->assertDontSee('Valor recebido')
            ->assertDontSee('name="method"', false);
    }

    public function test_a_cash_payment_shows_received_amount_and_change_including_zero_change(): void
    {
        $order = $this->pendingOrder();
        app(PaymentService::class)->pay($order, PaymentMethod::Cash, 3000);
        $this->get(route('orders.show', $order))->assertSeeInOrder(['Valor recebido', 'R$ 30,00', 'Troco', 'R$ 3,00'])->assertDontSee('Desconto');

        $exact = $this->pendingOrder();
        app(PaymentService::class)->pay($exact, PaymentMethod::Cash, 2700);
        $this->get(route('orders.show', $exact))->assertSeeInOrder(['Valor recebido', 'R$ 27,00', 'Troco', 'R$ 0,00']);
    }

    public function test_orders_without_payment_are_described_according_to_their_state(): void
    {
        $pending = $this->pendingOrder();
        $this->get(route('orders.show', $pending))->assertSee('Aguardando pagamento')->assertSee('Pagamento simulado — nenhuma cobrança real será realizada.');

        $cancelled = $this->orderForcedTo(OrderStatus::Cancelled);
        $this->get(route('orders.show', $cancelled))
            ->assertSee('Cancelado')
            ->assertSee('Pedido cancelado antes do pagamento')
            ->assertDontSee('Aguardando pagamento')
            ->assertDontSee('name="method"', false);
    }

    public function test_the_history_lists_the_recorded_transitions_in_chronological_order(): void
    {
        $order = $this->fullCycle();

        $this->get(route('orders.show', $order))
            ->assertSee('Histórico do pedido')
            ->assertSeeInOrder([
                'Aguardando pagamento → Pago',
                'Pago → Em preparação',
                'Em preparação → Pronto',
                'Pronto → Entregue',
            ]);
    }

    public function test_equal_timestamps_are_ordered_by_id(): void
    {
        $order = $this->fullCycle();
        OrderHistory::query()->update(['created_at' => '2026-01-01 10:00:00']);
        Notification::query()->update(['created_at' => '2026-01-01 10:00:00']);

        $this->get(route('orders.show', $order))->assertSeeInOrder([
            'Aguardando pagamento → Pago', 'Pago → Em preparação', 'Em preparação → Pronto', 'Pronto → Entregue',
        ])->assertSeeInOrder([
            'Pagamento do pedido confirmado.', 'Seu pedido está em preparação.', 'Seu pedido foi entregue.',
        ]);
    }

    public function test_orders_without_history_show_an_empty_state_and_no_invented_timeline(): void
    {
        $old = $this->orderForcedTo(OrderStatus::Delivered);

        $this->get(route('orders.show', $old))
            ->assertOk()
            ->assertSee('Nenhuma transição registrada para este pedido.')
            ->assertSee('Nenhuma notificação interna registrada.')
            ->assertDontSee('Aguardando pagamento → Pago')
            ->assertDontSee('→');
        $this->assertSame(0, OrderHistory::query()->count());
    }

    public function test_an_old_order_that_transitions_later_shows_only_the_new_records(): void
    {
        $old = $this->orderForcedTo(OrderStatus::Paid);
        app(OrderLifecycleService::class)->start($old);

        $this->get(route('orders.show', $old))
            ->assertSee('Pago → Em preparação')
            ->assertDontSee('Aguardando pagamento → Pago');
    }

    public function test_notifications_are_shown_separately_for_customer_and_kitchen(): void
    {
        $order = $this->fullCycle();

        $this->get(route('orders.show', $order))
            ->assertSee('Notificações internas')
            ->assertSeeInOrder(['Cliente', 'Pagamento do pedido confirmado.', 'Seu pedido está em preparação.', 'Seu pedido está pronto para entrega.', 'Seu pedido foi entregue.', 'Cozinha', 'Novo pedido pago aguardando preparação.', 'Pedido finalizado e pronto para entrega.']);
    }

    public function test_a_cancelled_order_shows_one_history_and_only_a_customer_notification(): void
    {
        $order = $this->pendingOrder();
        app(OrderLifecycleService::class)->cancel($order);

        $this->get(route('orders.show', $order))
            ->assertSee('Aguardando pagamento → Cancelado')
            ->assertSee('Seu pedido foi cancelado.')
            ->assertSee('Nenhuma notificação para a cozinha.')
            ->assertDontSee('name="method"', false);
    }

    public function test_persisted_messages_are_displayed_exactly_and_escaped(): void
    {
        $order = $this->pendingOrder();
        Notification::query()->create([
            'order_id' => $order->id, 'event_key' => 'x', 'audience' => NotificationAudience::Kitchen, 'message' => '<script>alert("x")</script>',
        ]);

        $this->get(route('orders.show', $order))
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false);
    }

    public function test_viewing_an_order_does_not_create_records_or_rerun_observers(): void
    {
        $order = $this->fullCycle();
        $spy = new class implements OrderObserver
        {
            public int $calls = 0;

            public function update(OrderStateChanged $event): void
            {
                $this->calls++;
            }
        };
        app(OrderEventPublisher::class)->subscribe($spy);
        $counts = [OrderHistory::query()->count(), Notification::query()->count()];
        $before = $order->fresh()->getAttributes();

        $this->get(route('orders.show', $order))->assertOk();
        $this->get(route('orders.show', $order))->assertOk();

        $this->assertSame(0, $spy->calls);
        $this->assertSame($counts, [OrderHistory::query()->count(), Notification::query()->count()]);
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_the_detail_loads_its_relationships_with_a_constant_number_of_queries(): void
    {
        $bare = $this->pendingOrder();
        DB::enableQueryLog();
        $this->get(route('orders.show', $bare))->assertOk();
        $bareQueries = count(DB::getQueryLog());

        $full = $this->fullCycle();
        DB::flushQueryLog();
        $this->get(route('orders.show', $full))->assertOk();

        $this->assertSame($bareQueries, count(DB::getQueryLog()), 'History and notifications must not add queries per record.');
    }

    public function test_an_unknown_order_returns_404(): void
    {
        $this->get('/orders/9999')->assertNotFound();
    }

    public function test_pages_never_ask_for_financial_data(): void
    {
        $order = $this->pendingOrder();

        foreach (['/', '/orders/create?product_id=1', '/orders', route('orders.show', $order)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('CVV')->assertDontSee('número do cartão', false)->assertDontSee('chave PIX');
        }
    }
}
