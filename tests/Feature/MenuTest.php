<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_lists_the_seeded_products_with_prices_in_reais(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Hambúrguer', 'R$ 20,00', 'Cachorro-quente', 'R$ 15,00', 'Batata frita', 'R$ 10,00']);
    }

    public function test_inactive_products_are_not_listed(): void
    {
        $this->seed(CatalogSeeder::class);
        Product::factory()->inactive()->create(['code' => 'secret', 'name' => 'Produto Oculto']);

        $this->get('/')->assertOk()->assertDontSee('Produto Oculto');
    }

    public function test_empty_catalog_shows_a_message_and_does_not_fail(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Nenhum produto disponível no momento.');
    }

    public function test_only_inactive_products_also_show_the_empty_message(): void
    {
        Product::factory()->inactive()->create();

        $this->get('/')->assertOk()->assertSee('Nenhum produto disponível no momento.');
    }

    public function test_product_names_are_escaped(): void
    {
        Product::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_product_code_must_be_unique(): void
    {
        Product::factory()->create(['code' => 'burger']);

        $this->expectException(QueryException::class);

        Product::factory()->create(['code' => 'burger']);
    }

    public function test_products_persist_price_in_integer_cents(): void
    {
        $product = Product::factory()->create(['base_price_cents' => 2000]);

        $this->assertSame(2000, $product->fresh()->base_price_cents);
        $this->assertTrue($product->fresh()->active);
    }
}
