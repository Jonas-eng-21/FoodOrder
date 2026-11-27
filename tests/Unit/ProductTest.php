<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    #[DataProvider('prices')]
    public function test_formatted_price_renders_cents_as_reais(int $cents, string $expected): void
    {
        $product = new Product(['base_price_cents' => $cents]);

        $this->assertSame($expected, $product->formatted_price);
    }

    /** @return array<string, array{int, string}> */
    public static function prices(): array
    {
        return [
            'whole reais' => [2000, 'R$ 20,00'],
            'cents only' => [5, 'R$ 0,05'],
            'mixed' => [1909, 'R$ 19,09'],
            'zero' => [0, 'R$ 0,00'],
            'thousands' => [123456, 'R$ 1.234,56'],
        ];
    }
}
