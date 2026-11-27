<?php

namespace Tests\Feature;

use App\Exceptions\InvalidOrderTransitionException;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real concurrency check: separate PHP processes, separate SQLite connections and a
 * shared temporary database file (never the development database).
 */
class PaymentConcurrencyTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = tempnam(sys_get_temp_dir(), 'foodorder-concurrency-').'.sqlite';
        touch($this->database);
    }

    protected function tearDown(): void
    {
        foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
            @unlink($this->database.$suffix);
        }

        parent::tearDown();
    }

    /** @return array<string, string> */
    private function environment(?string $transactionMode = null): array
    {
        return array_filter([
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->database,
            'DB_TRANSACTION_MODE' => $transactionMode,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'LOG_CHANNEL' => 'null',
        ]);
    }

    private function runArtisan(array $arguments): void
    {
        $process = new Process([PHP_BINARY, 'artisan', ...$arguments], dirname(__DIR__, 2), $this->environment());
        $process->mustRun();
    }

    private function prepareOrder(): int
    {
        $this->runArtisan(['migrate', '--force']);
        $this->runArtisan(['db:seed', '--force']);

        $pdo = new PDO('sqlite:'.$this->database);
        $pdo->exec("insert into orders (product_id, product_name_snapshot, extras_snapshot, subtotal_cents, status, created_at, updated_at) values (1, 'Hambúrguer', '[]', 2700, 'pending', datetime('now'), datetime('now'))");

        return (int) $pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    private function raceTwoProcesses(int $orderId, ?string $transactionMode = null): array
    {
        $startAt = microtime(true) + 1.5;
        $processes = array_map(function () use ($orderId, $startAt, $transactionMode): Process {
            $process = new Process(
                [PHP_BINARY, dirname(__DIR__).'/Support/pay_order_process.php', (string) $orderId, (string) $startAt, '400'],
                dirname(__DIR__, 2),
                $this->environment($transactionMode),
            );
            $process->start();

            return $process;
        }, [1, 2]);

        return array_map(function (Process $process): array {
            $process->wait();

            return json_decode($process->getOutput(), true) ?? ['result' => 'crashed', 'stderr' => $process->getErrorOutput()];
        }, $processes);
    }

    public function test_two_concurrent_payments_for_the_same_order_create_at_most_one_approved_payment(): void
    {
        $orderId = $this->prepareOrder();

        $results = $this->raceTwoProcesses($orderId);

        $pdo = new PDO('sqlite:'.$this->database);
        $this->assertSame(1, (int) $pdo->query('select count(*) from payments')->fetchColumn(), json_encode($results));
        $this->assertSame('paid', $pdo->query("select status from orders where id = {$orderId}")->fetchColumn());
        $this->assertSame(['paid', 'rejected'], collect($results)->pluck('result')->sort()->values()->all(), json_encode($results));

        // With BEGIN IMMEDIATE the loser waits for the lock, re-reads the order inside it and is turned
        // away by the State (never reaching the gateway); with a deferred transaction it would instead
        // fail later with a "database is locked" QueryException.
        $loser = collect($results)->firstWhere('result', 'rejected');
        $this->assertSame(InvalidOrderTransitionException::class, $loser['exception'], json_encode($results));
    }
}
