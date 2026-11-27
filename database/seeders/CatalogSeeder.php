<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Initial menu (RN01). Idempotent: products are matched by code and only
     * created when missing, so existing rows are never overwritten or duplicated.
     */
    private const PRODUCTS = [
        ['code' => 'burger', 'name' => 'Hambúrguer', 'base_price_cents' => 2000],
        ['code' => 'hotdog', 'name' => 'Cachorro-quente', 'base_price_cents' => 1500],
        ['code' => 'fries', 'name' => 'Batata frita', 'base_price_cents' => 1000],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $product) {
            Product::firstOrCreate(['code' => $product['code']], $product + ['active' => true]);
        }
    }
}
