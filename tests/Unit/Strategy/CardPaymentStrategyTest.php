<?php

namespace Tests\Unit\Strategy;

use App\Exceptions\InvalidPaymentException;
use App\Patterns\Strategy\CardPaymentStrategy;
use App\Patterns\Strategy\PaymentStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardPaymentStrategyTest extends TestCase
{
    /** @return array<string, array{int}> */
    public static function subtotals(): array
    {
        return ['2700' => [2700], '2000' => [2000], '1' => [1], 'zero' => [0]];
    }

    #[DataProvider('subtotals')]
    public function test_card_keeps_the_subtotal_without_discount_fee_or_change(int $subtotal): void
    {
        $quote = (new CardPaymentStrategy)->calculate($subtotal, null);

        $this->assertInstanceOf(PaymentStrategy::class, new CardPaymentStrategy);
        $this->assertSame($subtotal, $quote->totalCents);
        $this->assertSame(0, $quote->discountCents);
        $this->assertNull($quote->changeCents);
        $this->assertNull($quote->receivedCents);
    }

    public function test_card_ignores_a_received_amount(): void
    {
        $quote = (new CardPaymentStrategy)->calculate(2700, 5000);

        $this->assertSame(2700, $quote->totalCents);
        $this->assertNull($quote->changeCents);
    }

    public function test_card_rejects_a_negative_subtotal(): void
    {
        $this->expectException(InvalidPaymentException::class);

        (new CardPaymentStrategy)->calculate(-1, null);
    }
}
