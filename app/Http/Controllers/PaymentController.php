<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\InvalidPaymentException;
use App\Exceptions\PaymentRejectedException;
use App\Http\Requests\PayOrderRequest;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function store(PayOrderRequest $request, Order $order, PaymentService $payments): RedirectResponse
    {
        $show = redirect()->route('orders.show', $order);

        try {
            $payments->pay($order, $request->method(), $request->receivedCents());
        } catch (InvalidOrderTransitionException $e) {
            return $show->with('error', $e->getMessage());
        } catch (InvalidPaymentException|PaymentRejectedException $e) {
            return $show->withInput()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'database is locked')) {
                throw $e;
            }

            return $show->withInput()->with('error', 'O sistema está ocupado. Tente novamente em instantes.');
        }

        return $show->with('status', 'Pagamento simulado aprovado.');
    }
}
