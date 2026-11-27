<?php

namespace Tests\Feature;

use App\Exceptions\InvalidOrderTransitionException;
use Tests\Support\RunsConcurrentOrderActions;
use Tests\TestCase;

/**
 * Real concurrency check: two PHP processes paying the same order.
 */
class PaymentConcurrencyTest extends TestCase
{
    use RunsConcurrentOrderActions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpConcurrency();
    }

    protected function tearDown(): void
    {
        $this->tearDownConcurrency();
        parent::tearDown();
    }

    public function test_two_concurrent_payments_for_the_same_order_create_at_most_one_approved_payment(): void
    {
        $orderId = $this->prepareOrder();

        $results = $this->race($orderId, [
            ['action' => 'pay', 'delay' => 0.0, 'sleepMs' => 400],
            ['action' => 'pay', 'delay' => 0.0, 'sleepMs' => 400],
        ]);

        $this->assertSame(1, (int) $this->scalar('select count(*) from payments'), json_encode($results));
        $this->assertSame('paid', $this->scalar("select status from orders where id = {$orderId}"));
        $this->assertSame(['done', 'rejected'], collect($results)->pluck('result')->sort()->values()->all(), json_encode($results));

        // With BEGIN IMMEDIATE the loser waits for the lock, re-reads the order inside it and is turned
        // away by the State (never reaching the gateway); with a deferred transaction it would instead
        // fail later with a "database is locked" QueryException.
        $loser = collect($results)->firstWhere('result', 'rejected');
        $this->assertSame(InvalidOrderTransitionException::class, $loser['exception'], json_encode($results));
    }
}
