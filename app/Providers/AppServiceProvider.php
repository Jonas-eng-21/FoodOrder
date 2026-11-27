<?php

namespace App\Providers;

use App\Patterns\Strategy\CardPaymentStrategy;
use App\Patterns\Strategy\CashPaymentStrategy;
use App\Patterns\Strategy\PaymentCalculator;
use App\Patterns\Strategy\PixPaymentStrategy;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
