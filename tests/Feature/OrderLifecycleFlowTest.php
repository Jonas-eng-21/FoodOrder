<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderLifecycleFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function newOrder(): Order
    {
        $this->post('/orders', ['product_id' => 1, 'extras' => ['bacon', 'cheese']]);

        return Order::query()->latest('id')->firstOrFail();
    }

    private function orderIn(OrderStatus $status): Order
    {
        $order = $this->newOrder();

        if ($status !== OrderStatus::Pending && $status !== OrderStatus::Cancelled) {
            $this->post(route('orders.pay', $order), ['method' => 'pix']);
        }
        if (! in_array($status, [OrderStatus::Pending, OrderStatus::Paid], true)) {
            $order->refresh()->forceFill(['status' => $status])->save();
        }

        return $order->fresh();
    }

    public function test_complete_flow_from_creation_to_delivery(): void
    {
        $order = $this->newOrder();
        $this->assertSame(OrderStatus::Pending, $order->status);

        $this->post(route('orders.pay', $order), ['method' => 'pix'])->assertRedirect(route('orders.show', $order));
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $this->post(route('orders.start', $order))->assertRedirect(route('orders.show', $order))->assertSessionHas('status');
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);

        $this->post(route('orders.ready', $order))->assertRedirect(route('orders.show', $order))->assertSessionHas('status');
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);

        $this->post(route('orders.deliver', $order))->assertRedirect(route('orders.show', $order))->assertSessionHas('status');
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Entregue')
            ->assertSee('R$ 27,00')
            ->assertSee('R$ 25,65')
            ->assertSee(Payment::query()->firstOrFail()->simulated_reference)
            ->assertDontSee('name="method"', false)
            ->assertDontSee('/start', false)
            ->assertDontSee('/ready', false)
            ->assertDontSee('/deliver', false)
            ->assertDontSee('/cancel', false);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_cancellation_flow_rejects_every_later_attempt(): void
    {
        $order = $this->newOrder();

        $this->post(route('orders.cancel', $order))->assertRedirect(route('orders.show', $order))->assertSessionHas('status');
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);

        foreach (['pay' => ['method' => 'pix'], 'start' => [], 'ready' => [], 'deliver' => [], 'cancel' => []] as $action => $data) {
            $this->post(route("orders.{$action}", $order), $data)->assertRedirect(route('orders.show', $order))->assertSessionHas('error');
        }

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Cancelado')->assertDontSee('name="method"', false);
    }

    /** @return array<string, array{OrderStatus, list<string>, list<string>}> status, visible actions, hidden actions */
    public static function visibleActions(): array
    {
        $all = ['pay', 'start', 'ready', 'deliver', 'cancel'];

        return [
            'pending' => [OrderStatus::Pending, ['pay', 'cancel'], ['start', 'ready', 'deliver']],
            'paid' => [OrderStatus::Paid, ['start'], ['pay', 'ready', 'deliver', 'cancel']],
            'preparing' => [OrderStatus::Preparing, ['ready'], ['pay', 'start', 'deliver', 'cancel']],
            'ready' => [OrderStatus::Ready, ['deliver'], ['pay', 'start', 'ready', 'cancel']],
            'delivered' => [OrderStatus::Delivered, [], $all],
            'cancelled' => [OrderStatus::Cancelled, [], $all],
        ];
    }

    /**
     * @param  list<string>  $visible
     * @param  list<string>  $hidden
     */
    #[DataProvider('visibleActions')]
    public function test_the_detail_shows_only_the_actions_allowed_by_the_current_state(OrderStatus $status, array $visible, array $hidden): void
    {
        $order = $this->orderIn($status);
        $response = $this->get(route('orders.show', $order))->assertOk()->assertSee($status->label());

        foreach ($visible as $action) {
            $response->assertSee('/orders/'.$order->id.'/'.$action, false);
        }
        foreach ($hidden as $action) {
            $response->assertDontSee('/orders/'.$order->id.'/'.$action, false);
        }
    }

    /** @return array<string, array{OrderStatus, string}> */
    public static function invalidPosts(): array
    {
        $valid = ['pending' => ['cancel'], 'paid' => ['start'], 'preparing' => ['ready'], 'ready' => ['deliver']];
        $cases = [];
        foreach (OrderStatus::cases() as $status) {
            foreach (['start', 'ready', 'deliver', 'cancel'] as $action) {
                if (! in_array($action, $valid[$status->value] ?? [], true)) {
                    $cases["{$status->value} + {$action}"] = [$status, $action];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('invalidPosts')]
    public function test_direct_posts_are_rejected_even_when_the_button_is_not_visible(OrderStatus $status, string $action): void
    {
        $order = $this->orderIn($status);
        $paymentsBefore = Payment::query()->count();

        $this->post(route("orders.{$action}", $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame($status, $order->fresh()->status);
        $this->assertSame($paymentsBefore, Payment::query()->count());
    }

    public function test_forged_fields_cannot_change_the_status(): void
    {
        $order = $this->orderIn(OrderStatus::Paid);

        $this->post(route('orders.start', $order), ['status' => 'delivered', 'subtotal_cents' => 1, 'method' => 'cash'])
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Preparing, $fresh->status);
        $this->assertSame(2700, $fresh->subtotal_cents);
    }

    public function test_actions_on_unknown_orders_return_404(): void
    {
        foreach (['start', 'ready', 'deliver', 'cancel'] as $action) {
            $this->post("/orders/9999/{$action}")->assertNotFound();
        }
    }

    public function test_transitions_cannot_be_triggered_by_get(): void
    {
        $order = $this->orderIn(OrderStatus::Paid);

        foreach (['start', 'ready', 'deliver', 'cancel'] as $action) {
            $this->get("/orders/{$order->id}/{$action}")->assertStatus(405);
        }

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_action_forms_carry_a_csrf_token(): void
    {
        $order = $this->orderIn(OrderStatus::Paid);

        $this->get(route('orders.show', $order))->assertSee('name="_token"', false);
    }
}
