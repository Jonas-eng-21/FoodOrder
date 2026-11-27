<?php

namespace Tests\Unit\Strategy;

use App\Exceptions\InvalidPaymentException;
use App\Patterns\Strategy\CardPaymentStrategy;
use App\Patterns\Strategy\CashPaymentStrategy;
use App\Patterns\Strategy\PaymentCalculator;
use App\Patterns\Strategy\PaymentQuote;
use App\Patterns\Strategy\PaymentStrategy;
use App\Patterns\Strategy\PixPaymentStrategy;
use Tests\TestCase;

class PaymentCalculatorTest extends TestCase
{
    public function test_container_provides_a_calculator_wired_with_the_three_strategies(): void
    {
        $calculator = $this->app->make(PaymentCalculator::class);

        $this->assertSame(2565, $calculator->calculate('pix', 2700)->totalCents);
        $this->assertSame(2700, $calculator->calculate('card', 2700)->totalCents);
        $cash = $calculator->calculate('cash', 2700, 3000);
        $this->assertSame(2700, $cash->totalCents);
        $this->assertSame(300, $cash->changeCents);
    }

    public function test_context_delegates_to_the_selected_strategy_through_the_interface(): void
    {
        $spy = new class implements PaymentStrategy
        {
            /** @var list<array{int, int|null}> */
            public array $calls = [];

            public function calculate(int $subtotalCents, ?int $receivedCents): PaymentQuote
            {
                $this->calls[] = [$subtotalCents, $receivedCents];

                return new PaymentQuote($subtotalCents, 1, $subtotalCents - 1);
            }
        };
        $other = new CardPaymentStrategy;

        $calculator = new PaymentCalculator(['custom' => $spy, 'card' => $other]);
        $quote = $calculator->calculate('custom', 500, 700);

        $this->assertSame([[500, 700]], $spy->calls);
        $this->assertSame(1, $quote->totalCents);
    }

    public function test_strategies_are_interchangeable_under_the_same_context(): void
    {
        $strategies = ['pix' => new PixPaymentStrategy, 'card' => new CardPaymentStrategy, 'cash' => new CashPaymentStrategy];
        $calculator = new PaymentCalculator($strategies);

        foreach ($strategies as $method => $strategy) {
            $this->assertEquals($strategy->calculate(2700, 3000), $calculator->calculate($method, 2700, 3000));
        }
    }

    public function test_unknown_payment_methods_are_rejected(): void
    {
        $calculator = $this->app->make(PaymentCalculator::class);

        foreach (['', 'boleto', 'PIX', 'pix '] as $method) {
            try {
                $calculator->calculate($method, 2700);
                $this->fail("Method '{$method}' should be rejected.");
            } catch (InvalidPaymentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_repeated_quotes_for_the_same_input_are_equivalent(): void
    {
        $calculator = $this->app->make(PaymentCalculator::class);

        $this->assertEquals($calculator->calculate('pix', 2010), $calculator->calculate('pix', 2010));
    }
}
