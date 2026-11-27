<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyParseTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function valid(): array
    {
        return [
            'whole' => ['27', 2700],
            'one decimal' => ['27.5', 2750],
            'two decimals' => ['27.50', 2750],
            'comma' => ['30,00', 3000],
            'comma one decimal' => ['27,5', 2750],
            'cent' => ['0.01', 1],
            'zero' => ['0', 0],
            'large' => ['1234.56', 123456],
            'max' => ['9999999.99', 999999999],
        ];
    }

    #[DataProvider('valid')]
    public function test_parse_reais_converts_to_cents_without_floats(string $input, int $cents): void
    {
        $this->assertSame($cents, Money::parseReais($input));
    }

    /** @return array<string, array{string}> */
    public static function invalid(): array
    {
        return [
            'empty' => [''],
            'negative' => ['-1'],
            'scientific' => ['1e3'],
            'three decimals' => ['30.999'],
            'letters' => ['abc'],
            'leading space' => [' 30'],
            'leading dot' => ['.5'],
            'trailing dot' => ['5.'],
            'thousands separator' => ['1.000,00'],
            'two separators' => ['1.0.0'],
            'too many digits' => ['10000000'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_parse_reais_rejects_malformed_amounts(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::parseReais($input);
    }
}
