<?php

namespace Tests\Unit\State;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Patterns\State\CancelledState;
use App\Patterns\State\DeliveredState;
use App\Patterns\State\OrderState;
use App\Patterns\State\PaidState;
use App\Patterns\State\PendingState;
use App\Patterns\State\PreparingState;
use App\Patterns\State\ReadyState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The 30 state/operation combinations of guide section 5.4 (5 valid transitions, 25 rejections).
 */
class OrderStateMatrixTest extends TestCase
{
    private const OPERATIONS = ['pay', 'start', 'ready', 'deliver', 'cancel'];

    /** @return array<string, array{class-string<OrderState>, OrderStatus, string, OrderStatus|null}> */
    public static function matrix(): array
    {
        $table = [
            [PendingState::class, OrderStatus::Pending, ['pay' => OrderStatus::Paid, 'cancel' => OrderStatus::Cancelled]],
            [PaidState::class, OrderStatus::Paid, ['start' => OrderStatus::Preparing]],
            [PreparingState::class, OrderStatus::Preparing, ['ready' => OrderStatus::Ready]],
            [ReadyState::class, OrderStatus::Ready, ['deliver' => OrderStatus::Delivered]],
            [DeliveredState::class, OrderStatus::Delivered, []],
            [CancelledState::class, OrderStatus::Cancelled, []],
        ];

        $cases = [];
        foreach ($table as [$class, $status, $allowed]) {
            foreach (self::OPERATIONS as $operation) {
                $cases["{$status->value} + {$operation}"] = [$class, $status, $operation, $allowed[$operation] ?? null];
            }
        }

        return $cases;
    }

    /** @param class-string<OrderState> $class */
    #[DataProvider('matrix')]
    public function test_each_state_allows_only_its_own_transitions(string $class, OrderStatus $status, string $operation, ?OrderStatus $expected): void
    {
        $state = new $class;

        $this->assertInstanceOf(OrderState::class, $state);
        $this->assertSame($status, $state->status());

        $can = 'can'.ucfirst($operation);
        $this->assertSame($expected !== null, $state->$can(), "{$status->value}::{$can}()");

        if ($expected === null) {
            $this->expectException(InvalidOrderTransitionException::class);
            $state->$operation();
        }

        $next = $state->$operation();
        $this->assertInstanceOf(OrderState::class, $next);
        $this->assertSame($expected, $next->status());
        $this->assertSame($status, $state->status(), 'The current state must not change: transitions are pure.');
    }

    public function test_the_matrix_has_thirty_combinations_with_five_valid_transitions(): void
    {
        $cases = self::matrix();

        $this->assertCount(30, $cases);
        $this->assertCount(5, array_filter($cases, fn (array $case): bool => $case[3] !== null));
    }

    public function test_terminal_states_reject_every_operation(): void
    {
        foreach ([new DeliveredState, new CancelledState] as $state) {
            foreach (self::OPERATIONS as $operation) {
                try {
                    $state->$operation();
                    $this->fail("{$state->status()->value} must reject {$operation}.");
                } catch (InvalidOrderTransitionException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function test_the_happy_path_walks_through_all_states_in_order(): void
    {
        $state = new PendingState;
        $path = [$state->status()];

        foreach (['pay', 'start', 'ready', 'deliver'] as $operation) {
            $state = $state->$operation();
            $path[] = $state->status();
        }

        $this->assertSame(
            [OrderStatus::Pending, OrderStatus::Paid, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivered],
            $path,
        );
    }

    public function test_status_enum_has_six_persisted_identifiers_and_labels(): void
    {
        $this->assertSame(
            ['pending', 'paid', 'preparing', 'ready', 'delivered', 'cancelled'],
            array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::cases()),
        );

        foreach (OrderStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }
}
