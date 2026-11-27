<?php

namespace Tests\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\CatalogSeeder;

/** Test helpers to build orders through the real services or directly in a given status. */
trait BuildsOrders
{
    protected function seedCatalog(): void
    {
        $this->seed(CatalogSeeder::class);
    }

    protected function pendingOrder(): Order
    {
        return app(OrderService::class)->create(Product::query()->where('code', 'burger')->firstOrFail(), ['bacon', 'cheese']);
    }

    /** Creates an order and forces its status without going through the lifecycle (no events, no records). */
    protected function orderForcedTo(OrderStatus $status): Order
    {
        $order = $this->pendingOrder();
        $order->forceFill(['status' => $status])->save();

        return $order->fresh();
    }

    protected function payThroughService(Order $order, PaymentMethod $method = PaymentMethod::Pix): void
    {
        app(PaymentService::class)->pay($order, $method);
    }
}
