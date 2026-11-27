<?php

namespace Tests\Unit\State;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Patterns\State\OrderState;
use App\Patterns\State\PaidState;
use App\Patterns\State\PendingState;
use PHPUnit\Framework\TestCase;

class OrderStateTest extends TestCase
{
    public function test_pending_state_allows_payment_and_leads_to_paid(): void
    {
        $state = new PendingState;

        $this->assertInstanceOf(OrderState::class, $state);
        $this->assertSame(OrderStatus::Pending, $state->status());
        $this->assertTrue($state->canPay());
        $this->assertInstanceOf(PaidState::class, $state->pay());
        $this->assertSame(OrderStatus::Paid, $state->pay()->status());
    }

    public function test_paid_state_rejects_another_payment(): void
    {
        $state = new PaidState;

        $this->assertSame(OrderStatus::Paid, $state->status());
        $this->assertFalse($state->canPay());

        $this->expectException(InvalidOrderTransitionException::class);

        $state->pay();
    }

    public function test_order_rehydrates_its_state_from_the_persisted_status(): void
    {
        $pending = new Order;
        $pending->status = OrderStatus::Pending;
        $paid = new Order;
        $paid->status = OrderStatus::Paid;

        $this->assertInstanceOf(PendingState::class, $pending->state());
        $this->assertInstanceOf(PaidState::class, $paid->state());
        $this->assertTrue($pending->canBePaid());
        $this->assertFalse($paid->canBePaid());
    }

    public function test_rejected_transition_has_no_side_effects(): void
    {
        $order = new Order;
        $order->status = OrderStatus::Paid;

        try {
            $order->state()->pay();
            $this->fail('Expected InvalidOrderTransitionException.');
        } catch (InvalidOrderTransitionException) {
            $this->assertSame(OrderStatus::Paid, $order->status);
        }
    }
}
