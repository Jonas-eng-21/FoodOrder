<?php

namespace Tests\Unit\Strategy;

use App\Exceptions\InvalidPaymentException;
use App\Patterns\Strategy\CashPaymentStrategy;
use App\Patterns\Strategy\PaymentStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CashPaymentStrategyTest extends TestCase
{
    /** @return array<string, array{int, int, int}> subtotal, received, change */
    public static function validPayments(): array
    {
        return [
            'change of 300' => [2700, 3000, 300],
            'exact amount' => [2700, 2700, 0],
            'large note' => [2700, 5000, 2300],
            'free order' => [0, 0, 0],
        ];
    }

    #[DataProvider('validPayments')]
    public function test_cash_keeps_the_subtotal_and_calculates_change(int $subtotal, int $received, int $change): void
    {
        $quote = (new CashPaymentStrategy)->calculate($subtotal, $received);

        $this->assertInstanceOf(PaymentStrategy::class, new CashPaymentStrategy);
        $this->assertSame($subtotal, $quote->totalCents);
        $this->assertSame(0, $quote->discountCents);
        $this->assertSame($received, $quote->receivedCents);
        $this->assertSame($change, $quote->changeCents);
    }

    /** @return array<string, array{int, int|null}> */
    public static function invalidPayments(): array
    {
        return [
            'one cent short' => [2700, 2699],
            'zero' => [2700, 0],
            'negative' => [2700, -100],
            'missing' => [2700, null],
        ];
    }

    #[DataProvider('invalidPayments')]
    public function test_cash_rejects_missing_negative_or_insufficient_amounts(int $subtotal, ?int $received): void
    {
        $this->expectException(InvalidPaymentException::class);

        (new CashPaymentStrategy)->calculate($subtotal, $received);
    }

    public function test_cash_rejects_a_negative_subtotal(): void
    {
        $this->expectException(InvalidPaymentException::class);

        (new CashPaymentStrategy)->calculate(-100, 0);
    }
}
