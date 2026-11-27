<?php

/*
 * Test helper: runs one order action in an independent PHP process so that the SQLite
 * concurrency protection can be exercised with real, separate connections.
 *
 * Usage: php order_action_process.php <order-id> <start-at-unix-seconds> <sleep-ms> <action>
 *   action: pay | start | ready | deliver | cancel
 *   sleep-ms: how long the action holds the write lock (inside the gateway for "pay",
 *             inside the order save for the lifecycle actions).
 * Env FOODORDER_TEST_GATEWAY_MARKER: file created whenever the (fake) gateway is called.
 */

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Patterns\Adapter\PaymentGateway;
use App\Patterns\Adapter\PaymentResult;
use App\Patterns\Strategy\PaymentCalculator;
use App\Services\OrderLifecycleService;
use App\Services\PaymentService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $orderId, $startAt, $sleepMs, $action] = $argv + [null, null, null, 0, 'pay'];

$gateway = new class((int) $sleepMs, getenv('FOODORDER_TEST_GATEWAY_MARKER') ?: null) implements PaymentGateway
{
    public function __construct(private readonly int $sleepMs, private readonly ?string $marker) {}

    public function charge(int $totalCents): PaymentResult
    {
        if ($this->marker !== null) {
            touch($this->marker);
        }
        usleep($this->sleepMs * 1000);

        return PaymentResult::approved('SIMULATED-CONCURRENT-'.getmypid());
    }
};

if ($action !== 'pay') {
    Order::saving(function () use ($sleepMs): void {
        usleep(((int) $sleepMs) * 1000);
    });
}

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $order = Order::query()->findOrFail((int) $orderId);

    if ($action === 'pay') {
        (new PaymentService($app->make(PaymentCalculator::class), ['pix' => $gateway, 'card' => $gateway, 'cash' => $gateway]))
            ->pay($order, PaymentMethod::Pix);
    } else {
        $app->make(OrderLifecycleService::class)->{$action}($order);
    }

    echo json_encode(['result' => 'done', 'action' => $action]);
} catch (Throwable $e) {
    echo json_encode(['result' => 'rejected', 'action' => $action, 'exception' => $e::class]);
}
