<?php

namespace Tests\Feature;

use App\Exceptions\InvalidOrderTransitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RunsConcurrentOrderActions;
use Tests\TestCase;

/**
 * Real concurrency checks for lifecycle transitions (separate processes, shared temporary SQLite file).
 */
class OrderLifecycleConcurrencyTest extends TestCase
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

    public function test_payment_that_wins_the_lock_makes_the_concurrent_cancellation_fail(): void
    {
        $orderId = $this->prepareOrder('pending');

        $results = $this->race($orderId, [
            ['action' => 'pay', 'delay' => 0.0, 'sleepMs' => 600],
            ['action' => 'cancel', 'delay' => 0.25, 'sleepMs' => 0],
        ]);

        $this->assertSame('done', $results[0]['result'], json_encode($results));
        $this->assertSame(['result' => 'rejected', 'action' => 'cancel', 'exception' => InvalidOrderTransitionException::class], $results[1], json_encode($results));
        $this->assertSame('paid', $this->scalar("select status from orders where id = {$orderId}"));
        $this->assertSame(1, (int) $this->scalar('select count(*) from payments'));
        $this->assertSame([1, 1, 1], $this->effects(), 'Only the payment may produce effects (pending → paid).');
    }

    public function test_cancellation_that_wins_the_lock_rejects_the_payment_before_the_gateway(): void
    {
        $orderId = $this->prepareOrder('pending');

        $results = $this->race($orderId, [
            ['action' => 'cancel', 'delay' => 0.0, 'sleepMs' => 600],
            ['action' => 'pay', 'delay' => 0.25, 'sleepMs' => 0],
        ]);

        $this->assertSame('done', $results[0]['result'], json_encode($results));
        $this->assertSame(['result' => 'rejected', 'action' => 'pay', 'exception' => InvalidOrderTransitionException::class], $results[1], json_encode($results));
        $this->assertSame('cancelled', $this->scalar("select status from orders where id = {$orderId}"));
        $this->assertSame(0, (int) $this->scalar('select count(*) from payments'), 'A cancelled order must never have an approved payment.');
        $this->assertFileDoesNotExist($this->marker, 'The gateway must not be called for a cancelled order.');
        $this->assertSame([1, 1, 0], $this->effects(), 'Only the cancellation may produce effects (pending → cancelled).');
        $this->assertSame('pending_to_cancelled', $this->scalar('select event_key from notifications'));
    }

    /** @return array<string, array{string, string, string}> action, initial status, final status */
    public static function repeatedTransitions(): array
    {
        return [
            'start' => ['start', 'paid', 'preparing'],
            'ready' => ['ready', 'preparing', 'ready'],
            'deliver' => ['deliver', 'ready', 'delivered'],
            'cancel' => ['cancel', 'pending', 'cancelled'],
        ];
    }

    #[DataProvider('repeatedTransitions')]
    public function test_a_transition_requested_twice_concurrently_is_applied_once(string $action, string $initial, string $final): void
    {
        $orderId = $this->prepareOrder($initial);

        $results = $this->race($orderId, [
            ['action' => $action, 'delay' => 0.0, 'sleepMs' => 500],
            ['action' => $action, 'delay' => 0.0, 'sleepMs' => 500],
        ]);

        $this->assertSame(['done', 'rejected'], collect($results)->pluck('result')->sort()->values()->all(), json_encode($results));
        $this->assertSame(InvalidOrderTransitionException::class, collect($results)->firstWhere('result', 'rejected')['exception'], json_encode($results));
        $this->assertSame($final, $this->scalar("select status from orders where id = {$orderId}"));
        $this->assertSame(1, (int) $this->scalar('select count(*) from order_histories'), 'The losing request must not add a history record.');
        $this->assertSame(1, (int) $this->scalar("select count(*) from notifications where audience = 'customer'"));
    }

    public function test_a_failing_observer_rolls_everything_back_on_a_real_database_file(): void
    {
        $orderId = $this->prepareOrder('pending');

        $results = $this->race($orderId, [['action' => 'pay', 'delay' => 0.0, 'sleepMs' => 0]], failingObserver: true);

        $this->assertSame(['result' => 'rejected', 'action' => 'pay', 'exception' => \RuntimeException::class], $results[0], json_encode($results));
        $this->assertSame('pending', $this->scalar("select status from orders where id = {$orderId}"));
        $this->assertSame([0, 0, 0], [
            (int) $this->scalar('select count(*) from payments'),
            (int) $this->scalar('select count(*) from order_histories'),
            (int) $this->scalar('select count(*) from notifications'),
        ]);
    }

    /** @return array{int, int, int} histories, customer notifications, kitchen notifications */
    private function effects(): array
    {
        return [
            (int) $this->scalar('select count(*) from order_histories'),
            (int) $this->scalar("select count(*) from notifications where audience = 'customer'"),
            (int) $this->scalar("select count(*) from notifications where audience = 'kitchen'"),
        ];
    }
}
