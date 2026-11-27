<?php

namespace Tests\Unit\Strategy;

use App\Exceptions\InvalidPaymentException;
use App\Patterns\Strategy\PaymentStrategy;
use App\Patterns\Strategy\PixPaymentStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PixPaymentStrategyTest extends TestCase
{
    /** @return array<string, array{int, int, int}> subtotal, total, discount */
    public static function quotes(): array
    {
        return [
            '2700' => [2700, 2565, 135],
            '2000' => [2000, 1900, 100],
            '2900' => [2900, 2755, 145],
            '1500' => [1500, 1425, 75],
            '1000' => [1000, 950, 50],
            'half cent rounds up (2010)' => [2010, 1910, 100],
            'below half cent (2001)' => [2001, 1901, 100],
            'above half cent (2011)' => [2011, 1910, 101],
            'zero' => [0, 0, 0],
            'one cent' => [1, 1, 0],
            'ten cents (9.5 rounds up)' => [10, 10, 0],
            'twenty cents' => [20, 19, 1],
        ];
    }

    #[DataProvider('quotes')]
    public function test_pix_applies_five_percent_with_half_cent_rounded_up(int $subtotal, int $total, int $discount): void
    {
        $quote = (new PixPaymentStrategy)->calculate($subtotal, null);

        $this->assertSame($subtotal, $quote->subtotalCents);
        $this->assertSame($total, $quote->totalCents);
        $this->assertSame($discount, $quote->discountCents);
        $this->assertSame($subtotal, $quote->totalCents + $quote->discountCents);
        $this->assertGreaterThanOrEqual(0, $quote->totalCents);
        $this->assertNull($quote->changeCents);
        $this->assertNull($quote->receivedCents);
    }

    public function test_pix_is_a_payment_strategy_and_ignores_a_received_amount(): void
    {
        $strategy = new PixPaymentStrategy;

        $this->assertInstanceOf(PaymentStrategy::class, $strategy);
        $this->assertSame(2565, $strategy->calculate(2700, 9999)->totalCents);
        $this->assertNull($strategy->calculate(2700, 9999)->changeCents);
    }

    public function test_pix_rejects_a_negative_subtotal(): void
    {
        $this->expectException(InvalidPaymentException::class);

        (new PixPaymentStrategy)->calculate(-1, null);
    }

    public function test_pix_rejects_subtotals_that_would_overflow_integer_arithmetic(): void
    {
        $this->expectException(InvalidPaymentException::class);

        (new PixPaymentStrategy)->calculate(PHP_INT_MAX, null);
    }
}
