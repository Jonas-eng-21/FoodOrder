<?php

namespace Tests\Feature;

use App\Enums\NotificationAudience;
use App\Enums\OrderStatus;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderTrackingFlowTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
    }

    public function test_the_complete_flow_through_the_interface_shows_all_five_patterns(): void
    {
        $this->get('/')->assertOk()->assertSee('Hambúrguer')->assertSee('orders/create?product_id=1', false);
        $this->get('/orders/create?product_id=1')->assertOk()->assertSee('Bacon');

        $this->post('/orders', ['product_id' => 1, 'extras' => ['bacon', 'cheese']])->assertRedirect();
        $order = Order::query()->firstOrFail();

        $this->get('/orders')->assertOk()->assertSee('#'.$order->id)->assertSee('R$ 27,00')->assertSee('Aguardando pagamento');
        $this->get(route('orders.show', $order))->assertOk()->assertSee('name="method"', false)->assertSee('/cancel', false);

        $this->post(route('orders.pay', $order), ['method' => 'pix'])->assertRedirect();
        foreach (['start', 'ready', 'deliver'] as $action) {
            $this->post(route("orders.{$action}", $order))->assertRedirect();
        }

        $order = $order->fresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(2700, $order->subtotal_cents);
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(4, OrderHistory::query()->count());
        $this->assertSame(4, Notification::query()->where('audience', NotificationAudience::Customer)->count());
        $this->assertSame(2, Notification::query()->where('audience', NotificationAudience::Kitchen)->count());

        $this->get('/orders')->assertSee('Entregue')->assertSee(route('orders.show', $order), false);
        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Entregue')
            ->assertSee('Hambúrguer + Bacon + Queijo extra')
            ->assertSeeInOrder(['Subtotal original', 'R$ 27,00', 'Desconto', 'R$ 1,35', 'Total pago', 'R$ 25,65'])
            ->assertSee('Pronto → Entregue')
            ->assertSee('Seu pedido foi entregue.')
            ->assertSee('Pedido finalizado e pronto para entrega.')
            ->assertDontSee('name="method"', false)
            ->assertDontSee('/orders/'.$order->id.'/start', false)
            ->assertDontSee('/orders/'.$order->id.'/ready', false)
            ->assertDontSee('/orders/'.$order->id.'/deliver', false)
            ->assertDontSee('/orders/'.$order->id.'/cancel', false);

        $this->post(route('orders.cancel', $order))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(4, OrderHistory::query()->count());
    }

    public function test_the_cancellation_flow_is_visible_in_the_interface(): void
    {
        $this->post('/orders', ['product_id' => 2])->assertRedirect();
        $order = Order::query()->firstOrFail();

        $this->post(route('orders.cancel', $order))->assertRedirect();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([1, 1, 0], [
            OrderHistory::query()->count(),
            Notification::query()->where('audience', NotificationAudience::Customer)->count(),
            Notification::query()->where('audience', NotificationAudience::Kitchen)->count(),
        ]);
        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Cancelado')
            ->assertSee('Aguardando pagamento → Cancelado')
            ->assertSee('Seu pedido foi cancelado.')
            ->assertDontSee('name="method"', false)
            ->assertDontSee('/orders/'.$order->id.'/pay', false)
            ->assertDontSee('/orders/'.$order->id.'/start', false);
    }

    public function test_a_rejected_operation_is_explained_on_the_detail_page(): void
    {
        $order = $this->pendingOrder();

        $this->followingRedirects()->post(route('orders.start', $order))
            ->assertOk()
            ->assertSee('Este pedido não pode ter o preparo iniciado no estado')
            ->assertSee('Aguardando pagamento');
        $this->assertSame(0, OrderHistory::query()->count());
    }
}
