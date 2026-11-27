<?php

namespace Tests\Support;

use PDO;
use Symfony\Component\Process\Process;

/**
 * Shared infrastructure for real concurrency tests: separate PHP processes, separate SQLite
 * connections and a shared temporary database file (never the development database).
 */
trait RunsConcurrentOrderActions
{
    private string $database;

    private string $marker;

    protected function setUpConcurrency(): void
    {
        $this->database = tempnam(sys_get_temp_dir(), 'foodorder-concurrency-').'.sqlite';
        $this->marker = $this->database.'.gateway-called';
        touch($this->database);
    }

    protected function tearDownConcurrency(): void
    {
        foreach (['', '-journal', '-wal', '-shm', '.gateway-called'] as $suffix) {
            @unlink($this->database.$suffix);
        }
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
            'FOODORDER_TEST_GATEWAY_MARKER' => $this->marker,
        ]);
    }

    private function runArtisan(array $arguments): void
    {
        (new Process([PHP_BINARY, 'artisan', ...$arguments], dirname(__DIR__, 2), $this->environment()))->mustRun();
    }

    /** Migrates and seeds the temporary database and inserts one order in the given status. */
    private function prepareOrder(string $status = 'pending'): int
    {
        $this->runArtisan(['migrate', '--force']);
        $this->runArtisan(['db:seed', '--force']);

        $pdo = new PDO('sqlite:'.$this->database);
        $pdo->exec("insert into orders (product_id, product_name_snapshot, extras_snapshot, subtotal_cents, status, created_at, updated_at) values (1, 'Hambúrguer', '[]', 2700, '{$status}', datetime('now'), datetime('now'))");
        $orderId = (int) $pdo->lastInsertId();

        if (in_array($status, ['paid', 'preparing', 'ready', 'delivered'], true)) {
            $pdo->exec("insert into payments (order_id, method, total_cents, simulated_reference, created_at, updated_at) values ({$orderId}, 'pix', 2565, 'SIMULATED-SEED', datetime('now'), datetime('now'))");
        }

        return $orderId;
    }

    /**
     * Starts one process per action and waits for all of them.
     *
     * @param  list<array{action: string, delay: float, sleepMs: int}>  $children  delay (seconds) is applied after the shared barrier
     * @return list<array<string, mixed>>
     */
    private function race(int $orderId, array $children, ?string $transactionMode = null): array
    {
        $barrier = microtime(true) + 2.0;

        $processes = array_map(function (array $child) use ($orderId, $barrier, $transactionMode): Process {
            $process = new Process(
                [PHP_BINARY, dirname(__DIR__).'/Support/order_action_process.php', (string) $orderId, (string) ($barrier + $child['delay']), (string) $child['sleepMs'], $child['action']],
                dirname(__DIR__, 2),
                $this->environment($transactionMode),
            );
            $process->start();

            return $process;
        }, $children);

        return array_map(function (Process $process): array {
            $process->wait();

            return json_decode($process->getOutput(), true) ?? ['result' => 'crashed', 'stderr' => $process->getErrorOutput()];
        }, $processes);
    }

    private function scalar(string $sql): mixed
    {
        return (new PDO('sqlite:'.$this->database))->query($sql)->fetchColumn();
    }
}
