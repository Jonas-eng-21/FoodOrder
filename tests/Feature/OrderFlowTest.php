<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function product(string $code): Product
    {
        return Product::query()->where('code', $code)->firstOrFail();
    }

    public function test_menu_links_active_products_to_the_order_form(): void
    {
        $inactive = Product::factory()->inactive()->create();

        $this->get('/')
            ->assertOk()
            ->assertSee('orders/create?product_id='.$this->product('burger')->id, false)
            ->assertDontSee('orders/create?product_id='.$inactive->id, false);
    }

    public function test_form_shows_product_price_and_permitted_extras(): void
    {
        $this->get('/orders/create?product_id='.$this->product('burger')->id)
            ->assertOk()
            ->assertSee('Hambúrguer')
            ->assertSee('R$ 20,00')
            ->assertSee('Bacon')
            ->assertSee('R$ 4,00')
            ->assertSee('Queijo extra')
            ->assertSee('R$ 3,00')
            ->assertSee('Ovo')
            ->assertSee('R$ 2,00')
            ->assertSee('name="_token"', false);
    }

    public function test_form_for_fries_offers_no_extras(): void
    {
        $this->get('/orders/create?product_id='.$this->product('fries')->id)
            ->assertOk()
            ->assertSee('Batata frita')
            ->assertDontSee('Bacon')
            ->assertDontSee('name="extras[]"', false);
    }

    public function test_form_rejects_inactive_nonexistent_and_missing_products(): void
    {
        $inactive = Product::factory()->inactive()->create();

        foreach (['?product_id='.$inactive->id, '?product_id=9999', '?product_id=abc', ''] as $query) {
            $this->get('/orders/create'.$query)
                ->assertRedirect('/')
                ->assertSessionHas('error');
        }
    }

    public function test_valid_order_is_created_and_redirects_to_the_detail(): void
    {
        $response = $this->post('/orders', [
            'product_id' => $this->product('burger')->id,
            'extras' => ['bacon', 'cheese', 'egg'],
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame(2900, $order->subtotal_cents);
        $this->assertSame('pending', $order->status->value);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Hambúrguer + Bacon + Queijo extra + Ovo')
            ->assertSee('R$ 29,00')
            ->assertSee('pending');
    }

    public function test_order_without_extras_is_pending_with_base_price(): void
    {
        $this->post('/orders', ['product_id' => $this->product('burger')->id])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(2000, $order->subtotal_cents);
        $this->assertSame([], $order->extras_snapshot);
    }

    public function test_forged_price_and_status_are_ignored(): void
    {
        $this->post('/orders', [
            'product_id' => $this->product('burger')->id,
            'extras' => ['bacon'],
            'subtotal_cents' => 1,
            'base_price_cents' => 1,
            'price' => 1,
            'status' => 'delivered',
            'product_name_snapshot' => 'Hack',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(2400, $order->subtotal_cents);
        $this->assertSame('pending', $order->status->value);
        $this->assertSame('Hambúrguer', $order->product_name_snapshot);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): array
    {
        return [
            'fries with extra' => [['code' => 'fries', 'extras' => ['bacon']], 'extras.0'],
            'unknown extra' => [['code' => 'burger', 'extras' => ['ketchup']], 'extras.0'],
            'duplicate extra' => [['code' => 'burger', 'extras' => ['bacon', 'bacon']], 'extras.0'],
            'extras not an array' => [['code' => 'burger', 'extras' => 'bacon'], 'extras'],
            'extra is an array' => [['code' => 'burger', 'extras' => [['bacon']]], 'extras.0'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_extras_are_rejected_without_persisting(array $payload, string $errorKey): void
    {
        $code = $payload['code'];
        unset($payload['code']);

        $this->post('/orders', ['product_id' => $this->product($code)->id] + $payload)
            ->assertSessionHasErrors($errorKey);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_inactive_nonexistent_and_missing_products_cannot_be_ordered(): void
    {
        $inactive = Product::factory()->inactive()->create();

        foreach ([$inactive->id, 9999, 'abc', null, ['1']] as $productId) {
            $this->post('/orders', ['product_id' => $productId])->assertSessionHasErrors('product_id');
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_product_deactivated_after_opening_the_form_is_rejected_on_confirmation(): void
    {
        $product = $this->product('burger');
        $this->get('/orders/create?product_id='.$product->id)->assertOk();

        $product->update(['active' => false]);

        $this->post('/orders', ['product_id' => $product->id])->assertSessionHasErrors('product_id');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_detail_keeps_the_snapshot_after_catalog_changes(): void
    {
        $product = $this->product('burger');
        $this->post('/orders', ['product_id' => $product->id, 'extras' => ['bacon']]);
        $order = Order::query()->firstOrFail();

        $product->update(['name' => 'Renomeado', 'base_price_cents' => 5000]);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Hambúrguer + Bacon')
            ->assertSee('R$ 24,00')
            ->assertDontSee('Renomeado');
    }

    public function test_unknown_order_returns_404(): void
    {
        $this->get('/orders/9999')->assertNotFound();
    }

    public function test_orders_cannot_be_created_through_get(): void
    {
        $this->get('/orders')->assertStatus(405);
        $this->assertSame(0, Order::query()->count());
    }
}
