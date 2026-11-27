<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\InvalidPaymentException;
use App\Exceptions\PaymentRejectedException;
use App\Models\Order;
use App\Models\Payment;
use App\Patterns\Adapter\PaymentGateway;
use App\Patterns\Adapter\PaymentResult;
use App\Patterns\Strategy\PaymentCalculator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orchestrates the simulated payment: State (eligibility/transition) → Strategy (quote) →
 * Adapter (gateway) → persistence. It knows only the PaymentGateway contract.
 *
 * Concurrency (SQLite): the whole operation runs in a transaction opened with BEGIN IMMEDIATE
 * (config/database.php, transaction_mode), which takes the database write lock up front. A competing
 * writer waits (busy_timeout); once it enters it re-reads the order inside the lock and finds it
 * already paid, so the gateway is never called twice. payments.order_id is UNIQUE as a last defence.
 */
class PaymentService
{
    /** @param array<string, PaymentGateway> $gateways gateways keyed by payment method value */
    public function __construct(
        private readonly PaymentCalculator $calculator,
        private readonly array $gateways,
    ) {}

    /**
     * @throws InvalidOrderTransitionException when the order cannot be paid in its current state
     * @throws InvalidPaymentException when the method or amounts are invalid
     * @throws PaymentRejectedException when the simulated gateway rejects or fails
     */
    public function pay(Order $order, PaymentMethod $method, ?int $receivedCents = null): Payment
    {
        return DB::transaction(function () use ($order, $method, $receivedCents): Payment {
            $order = Order::query()->findOrFail($order->getKey());

            $nextState = $order->state()->pay();

            $quote = $this->calculator->calculate($method->value, $order->subtotal_cents, $receivedCents);
            $result = $this->charge($method, $quote->totalCents);

            if (! $result->approved) {
                throw PaymentRejectedException::because($result->error ?? 'motivo não informado.');
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'method' => $method,
                'total_cents' => $quote->totalCents,
                'received_cents' => $quote->receivedCents,
                'change_cents' => $quote->changeCents,
                'simulated_reference' => $result->reference,
            ]);

            $order->transitionTo($nextState);

            return $payment;
        });
    }

    private function charge(PaymentMethod $method, int $totalCents): PaymentResult
    {
        $gateway = $this->gateways[$method->value] ?? throw InvalidPaymentException::unsupportedMethod($method->value);

        try {
            return $gateway->charge($totalCents);
        } catch (Throwable $e) {
            report($e);

            throw PaymentRejectedException::gatewayFailure();
        }
    }
}
