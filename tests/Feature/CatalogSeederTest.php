<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_initial_catalog(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame(
            ['burger' => 2000, 'hotdog' => 1500, 'fries' => 1000],
            Product::query()->orderBy('id')->pluck('base_price_cents', 'code')->all(),
        );
        $this->assertSame(3, Product::query()->where('active', true)->count());
    }

    public function test_running_the_seeder_repeatedly_does_not_duplicate_products(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->assertSame(3, Product::query()->count());
    }

    public function test_seeder_does_not_overwrite_existing_product_data(): void
    {
        $this->seed(CatalogSeeder::class);
        Product::query()->where('code', 'burger')->update(['base_price_cents' => 2500, 'active' => false]);

        $this->seed(CatalogSeeder::class);

        $burger = Product::query()->where('code', 'burger')->firstOrFail();
        $this->assertSame(2500, $burger->base_price_cents);
        $this->assertFalse($burger->active);
        $this->assertSame(3, Product::query()->count());
    }

    public function test_database_seeder_provisions_the_catalog(): void
    {
        $this->seed();

        $this->assertSame(3, Product::query()->count());
    }
}
