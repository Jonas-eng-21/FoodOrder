<?php

/*
 * Test helper: pays an order in an independent PHP process so that the SQLite
 * concurrency protection can be exercised with real, separate connections.
 *
 * Usage: php pay_order_process.php <order-id> <start-at-unix-micro> [sleep-ms-inside-gateway]
 */

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Patterns\Adapter\PaymentGateway;
use App\Patterns\Adapter\PaymentResult;
use App\Patterns\Strategy\PaymentCalculator;
use App\Services\PaymentService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $orderId, $startAt, $sleepMs] = $argv + [null, null, null, 0];

$gateway = new class((int) $sleepMs) implements PaymentGateway
{
    public function __construct(private readonly int $sleepMs) {}

    public function charge(int $totalCents): PaymentResult
    {
        usleep($this->sleepMs * 1000);

        return PaymentResult::approved('SIMULATED-CONCURRENT-'.getmypid());
    }
};

$service = new PaymentService($app->make(PaymentCalculator::class), ['pix' => $gateway, 'card' => $gateway, 'cash' => $gateway]);

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $service->pay(Order::query()->findOrFail((int) $orderId), PaymentMethod::Pix);
    echo json_encode(['result' => 'paid']);
} catch (Throwable $e) {
    echo json_encode(['result' => 'rejected', 'exception' => $e::class]);
}
