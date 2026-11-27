<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

class OrderLifecycleController extends Controller
{
    public function start(Order $order, OrderLifecycleService $lifecycle): RedirectResponse
    {
        return $this->run($order, fn () => $lifecycle->start($order), 'Preparo iniciado.');
    }

    public function ready(Order $order, OrderLifecycleService $lifecycle): RedirectResponse
    {
        return $this->run($order, fn () => $lifecycle->ready($order), 'Preparo finalizado: o pedido está pronto.');
    }

    public function deliver(Order $order, OrderLifecycleService $lifecycle): RedirectResponse
    {
        return $this->run($order, fn () => $lifecycle->deliver($order), 'Pedido entregue.');
    }

    public function cancel(Order $order, OrderLifecycleService $lifecycle): RedirectResponse
    {
        return $this->run($order, fn () => $lifecycle->cancel($order), 'Pedido cancelado.');
    }

    private function run(Order $order, Closure $action, string $success): RedirectResponse
    {
        $show = redirect()->route('orders.show', $order);

        try {
            $action();
        } catch (InvalidOrderTransitionException $e) {
            return $show->with('error', $e->getMessage());
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'database is locked')) {
                throw $e;
            }

            return $show->with('error', 'O sistema está ocupado. Tente novamente em instantes.');
        }

        return $show->with('status', $success);
    }
}
