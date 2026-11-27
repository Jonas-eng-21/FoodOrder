<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Patterns\Decorator\ExtraCatalog;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('orders.index', [
            'orders' => Order::query()->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }

    public function create(Request $request, ExtraCatalog $catalog): View|RedirectResponse
    {
        $productId = $request->query('product_id');
        $product = is_string($productId) && ctype_digit($productId)
            ? Product::active()->find((int) $productId)
            : null;

        if ($product === null) {
            return redirect()->route('menu.index')->with('error', 'O produto selecionado não está disponível.');
        }

        return view('orders.create', [
            'product' => $product,
            'extras' => $catalog->allowedFor($product->code),
        ]);
    }

    public function store(StoreOrderRequest $request, OrderService $orders): RedirectResponse
    {
        $product = Product::query()->findOrFail($request->validated('product_id'));

        $order = $orders->create($product, $request->validated('extras') ?? []);

        return redirect()->route('orders.show', $order)->with('status', 'Pedido criado com sucesso.');
    }

    public function show(Order $order): View
    {
        return view('orders.show', ['order' => $order->load('payment')]);
    }
}
