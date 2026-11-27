<?php

namespace Tests\Unit\Strategy;

use App\Exceptions\InvalidPaymentException;
use App\Patterns\Strategy\PaymentQuote;
use PHPUnit\Framework\TestCase;

class PaymentQuoteTest extends TestCase
{
    public function test_quote_exposes_immutable_integer_values(): void
    {
        $quote = new PaymentQuote(subtotalCents: 2700, totalCents: 2700, discountCents: 0, receivedCents: 3000, changeCents: 300);

        $this->assertSame([2700, 2700, 0, 3000, 300], [
            $quote->subtotalCents, $quote->totalCents, $quote->discountCents, $quote->receivedCents, $quote->changeCents,
        ]);
        $this->assertTrue((new \ReflectionClass($quote))->isReadOnly());
    }

    public function test_quote_rejects_negative_values(): void
    {
        $this->expectException(InvalidPaymentException::class);

        new PaymentQuote(subtotalCents: 100, totalCents: -1, discountCents: 101);
    }

    public function test_quote_rejects_inconsistent_totals(): void
    {
        $this->expectException(InvalidPaymentException::class);

        new PaymentQuote(subtotalCents: 2700, totalCents: 2500, discountCents: 100);
    }

    public function test_quote_rejects_inconsistent_change(): void
    {
        $this->expectException(InvalidPaymentException::class);

        new PaymentQuote(subtotalCents: 2700, totalCents: 2700, discountCents: 0, receivedCents: 3000, changeCents: 299);
    }
}
