<?php

namespace Tests\Unit\Adapter;

use App\Exceptions\InvalidPaymentException;
use App\Patterns\Adapter\PaymentResult;
use PHPUnit\Framework\TestCase;

class PaymentResultTest extends TestCase
{
    public function test_approved_result_carries_a_reference_and_no_error(): void
    {
        $result = PaymentResult::approved('SIMULATED-X-1');

        $this->assertTrue($result->approved);
        $this->assertSame('SIMULATED-X-1', $result->reference);
        $this->assertNull($result->error);
    }

    public function test_rejected_result_carries_an_error_and_no_reference(): void
    {
        $result = PaymentResult::rejected('declined');

        $this->assertFalse($result->approved);
        $this->assertNull($result->reference);
        $this->assertSame('declined', $result->error);
    }

    public function test_inconsistent_results_are_rejected(): void
    {
        foreach ([
            fn () => new PaymentResult(true, null, null),
            fn () => new PaymentResult(true, '', null),
            fn () => new PaymentResult(true, 'REF', 'error'),
            fn () => new PaymentResult(false, null, null),
            fn () => new PaymentResult(false, 'REF', 'error'),
            fn () => PaymentResult::approved('   '),
        ] as $build) {
            try {
                $build();
                $this->fail('Expected InvalidPaymentException.');
            } catch (InvalidPaymentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
