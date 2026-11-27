<?php

namespace App\Providers;

use App\Enums\PaymentMethod;
use App\Patterns\Adapter\CardGatewayAdapter;
use App\Patterns\Adapter\CashPaymentGateway;
use App\Patterns\Adapter\LegacyCardGateway;
use App\Patterns\Adapter\LegacyPixGateway;
use App\Patterns\Adapter\PixGatewayAdapter;
use App\Patterns\Observer\CustomerNotificationObserver;
use App\Patterns\Observer\HistoryObserver;
use App\Patterns\Observer\KitchenNotificationObserver;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\Strategy\CardPaymentStrategy;
use App\Patterns\Strategy\CashPaymentStrategy;
use App\Patterns\Strategy\PaymentCalculator;
use App\Patterns\Strategy\PixPaymentStrategy;
use App\Services\PaymentService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentCalculator::class, fn (): PaymentCalculator => new PaymentCalculator([
            'pix' => new PixPaymentStrategy,
            'card' => new CardPaymentStrategy,
            'cash' => new CashPaymentStrategy,
        ]));

        // Observer composition: one shared publisher; the observers are wired here, never by the model or services.
        $this->app->singleton(OrderEventPublisher::class, function ($app): OrderEventPublisher {
            $publisher = new OrderEventPublisher;
            $publisher->subscribe(new HistoryObserver);
            $publisher->subscribe(new CustomerNotificationObserver);
            $publisher->subscribe(new KitchenNotificationObserver);

            return $publisher;
        });

        $this->app->bind(PaymentService::class, fn ($app): PaymentService => new PaymentService($app->make(PaymentCalculator::class), [
            PaymentMethod::Pix->value => new PixGatewayAdapter(new LegacyPixGateway),
            PaymentMethod::Card->value => new CardGatewayAdapter(new LegacyCardGateway),
            PaymentMethod::Cash->value => new CashPaymentGateway,
        ], $app->make(OrderEventPublisher::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
