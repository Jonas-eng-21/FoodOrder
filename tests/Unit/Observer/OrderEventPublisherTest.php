<?php

namespace Tests\Unit\Observer;

use App\Enums\OrderStatus;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\Observer\OrderObserver;
use App\Patterns\Observer\OrderStateChanged;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OrderEventPublisherTest extends TestCase
{
    private function spy(): OrderObserver
    {
        return new class implements OrderObserver
        {
            /** @var list<OrderStateChanged> */
            public array $received = [];

            public function update(OrderStateChanged $event): void
            {
                $this->received[] = $event;
            }
        };
    }

    private function event(): OrderStateChanged
    {
        return new OrderStateChanged(7, OrderStatus::Pending, OrderStatus::Paid);
    }

    public function test_event_is_an_immutable_value_object(): void
    {
        $event = $this->event();

        $this->assertSame([7, OrderStatus::Pending, OrderStatus::Paid], [$event->orderId, $event->previousStatus, $event->newStatus]);
        $this->assertTrue((new \ReflectionClass($event))->isReadOnly());
    }

    public function test_a_subscribed_observer_receives_the_event(): void
    {
        $publisher = new OrderEventPublisher;
        $observer = $this->spy();
        $publisher->subscribe($observer);

        $event = $this->event();
        $publisher->notify($event);

        $this->assertSame([$event], $observer->received);
    }

    public function test_every_subscribed_observer_receives_the_same_event_through_the_interface(): void
    {
        $publisher = new OrderEventPublisher;
        $observers = [$this->spy(), $this->spy(), $this->spy()];
        foreach ($observers as $observer) {
            $publisher->subscribe($observer);
        }

        $event = $this->event();
        $publisher->notify($event);

        foreach ($observers as $observer) {
            $this->assertSame([$event], $observer->received);
        }
    }

    public function test_observers_are_notified_in_subscription_order(): void
    {
        $publisher = new OrderEventPublisher;
        $calls = [];
        foreach (['first', 'second', 'third'] as $name) {
            $publisher->subscribe(new class($name, $calls) implements OrderObserver
            {
                public function __construct(private readonly string $name, private array &$calls) {}

                public function update(OrderStateChanged $event): void
                {
                    $this->calls[] = $this->name;
                }
            });
        }

        $publisher->notify($this->event());

        $this->assertSame(['first', 'second', 'third'], $calls);
    }

    public function test_an_unsubscribed_observer_stops_receiving_events(): void
    {
        $publisher = new OrderEventPublisher;
        $kept = $this->spy();
        $removed = $this->spy();
        $publisher->subscribe($kept);
        $publisher->subscribe($removed);

        $publisher->notify($this->event());
        $publisher->unsubscribe($removed);
        $publisher->notify($this->event());

        $this->assertCount(2, $kept->received);
        $this->assertCount(1, $removed->received);
    }

    public function test_subscribing_the_same_instance_twice_does_not_duplicate_calls(): void
    {
        $publisher = new OrderEventPublisher;
        $observer = $this->spy();

        $publisher->subscribe($observer);
        $publisher->subscribe($observer);
        $publisher->notify($this->event());

        $this->assertCount(1, $observer->received);
        $this->assertCount(1, $publisher->observers());
    }

    public function test_two_distinct_instances_of_the_same_class_are_both_notified(): void
    {
        $publisher = new OrderEventPublisher;
        $a = $this->spy();
        $b = clone $a;
        $publisher->subscribe($a);
        $publisher->subscribe($b);

        $publisher->notify($this->event());

        $this->assertCount(1, $a->received);
        $this->assertCount(1, $b->received);
    }

    public function test_notifying_without_observers_does_nothing(): void
    {
        $publisher = new OrderEventPublisher;

        $publisher->notify($this->event());

        $this->assertSame([], $publisher->observers());
    }

    public function test_unsubscribing_an_unknown_observer_is_safe(): void
    {
        $publisher = new OrderEventPublisher;
        $subscribed = $this->spy();
        $publisher->subscribe($subscribed);

        $publisher->unsubscribe($this->spy());

        $this->assertCount(1, $publisher->observers());
    }

    public function test_observer_exceptions_propagate_and_stop_later_observers(): void
    {
        $publisher = new OrderEventPublisher;
        $before = $this->spy();
        $after = $this->spy();
        $publisher->subscribe($before);
        $publisher->subscribe(new class implements OrderObserver
        {
            public function update(OrderStateChanged $event): void
            {
                throw new RuntimeException('boom');
            }
        });
        $publisher->subscribe($after);

        try {
            $publisher->notify($this->event());
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertCount(1, $before->received);
        $this->assertSame([], $after->received);
    }
}
