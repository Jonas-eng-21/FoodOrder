<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_format_renders_cents_as_reais(int $cents, string $expected): void
    {
        $this->assertSame($expected, Money::format($cents));
    }

    /** @return array<string, array{int, string}> */
    public static function amounts(): array
    {
        return [
            'whole reais' => [2000, 'R$ 20,00'],
            'cents only' => [5, 'R$ 0,05'],
            'zero' => [0, 'R$ 0,00'],
            'thousands' => [123456, 'R$ 1.234,56'],
        ];
    }

    public function test_negative_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::format(-1);
    }
}
