@extends('layouts.app')

@section('title', 'Pedido #'.$order->id.' — '.config('app.name'))

@section('content')
    <h1 class="h3 mb-4">Pedido #{{ $order->id }}</h1>

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Produto</dt>
                <dd class="col-sm-9">{{ $order->product_name_snapshot }}</dd>

                <dt class="col-sm-3">Complementos</dt>
                <dd class="col-sm-9">
                    @forelse ($order->extras_snapshot as $extra)
                        {{ $extra['name'] }} ({{ \App\Support\Money::format($extra['price_cents']) }})@unless ($loop->last), @endunless
                    @empty
                        Nenhum
                    @endforelse
                </dd>

                <dt class="col-sm-3">Descrição</dt>
                <dd class="col-sm-9">{{ $order->description }}</dd>

                <dt class="col-sm-3">Subtotal</dt>
                <dd class="col-sm-9 fw-semibold">{{ $order->formatted_subtotal }}</dd>

                <dt class="col-sm-3">Estado</dt>
                <dd class="col-sm-9"><span class="badge {{ $order->payment ? 'text-bg-success' : 'text-bg-warning' }}">{{ $order->status->value }}</span></dd>
            </dl>
        </div>
        <div class="card-footer bg-transparent">
            <a class="btn btn-outline-secondary" href="{{ route('menu.index') }}">Voltar ao cardápio</a>
        </div>
    </div>

    @if ($order->payment)
        @php($payment = $order->payment)
        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h5">Pagamento</h2>
                <dl class="row mb-3">
                    <dt class="col-sm-3">Método</dt>
                    <dd class="col-sm-9">{{ $payment->method->label() }}</dd>

                    <dt class="col-sm-3">Subtotal original</dt>
                    <dd class="col-sm-9">{{ $order->formatted_subtotal }}</dd>

                    @if ($payment->total_cents < $order->subtotal_cents)
                        <dt class="col-sm-3">Desconto</dt>
                        <dd class="col-sm-9">{{ \App\Support\Money::format($order->subtotal_cents - $payment->total_cents) }}</dd>
                    @endif

                    <dt class="col-sm-3">Total pago</dt>
                    <dd class="col-sm-9 fw-semibold">{{ \App\Support\Money::format($payment->total_cents) }}</dd>

                    @if ($payment->received_cents !== null)
                        <dt class="col-sm-3">Valor recebido</dt>
                        <dd class="col-sm-9">{{ \App\Support\Money::format($payment->received_cents) }}</dd>

                        <dt class="col-sm-3">Troco</dt>
                        <dd class="col-sm-9">{{ \App\Support\Money::format($payment->change_cents) }}</dd>
                    @endif

                    <dt class="col-sm-3">Referência</dt>
                    <dd class="col-sm-9"><code>{{ $payment->simulated_reference }}</code></dd>
                </dl>
                <p class="text-body-secondary mb-0">Pagamento exclusivamente simulado: nenhuma cobrança real foi realizada.</p>
            </div>
        </div>
    @elseif ($order->canBePaid())
        <form method="POST" action="{{ route('orders.pay', $order) }}" class="card shadow-sm mt-4">
            @csrf
            <div class="card-body">
                <h2 class="h5">Pagamento</h2>
                <div class="alert alert-info" role="note">
                    Pagamento simulado — nenhuma cobrança real será realizada. Nenhum dado de cartão ou de conta bancária é solicitado.
                </div>

                @error('method')<div class="text-danger mb-2">{{ $message }}</div>@enderror

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="method" id="method-pix" value="pix" @checked(old('method') === 'pix')>
                    <label class="form-check-label" for="method-pix">PIX (5% de desconto)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="method" id="method-card" value="card" @checked(old('method') === 'card')>
                    <label class="form-check-label" for="method-card">Cartão (valor integral)</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="method" id="method-cash" value="cash" @checked(old('method') === 'cash')>
                    <label class="form-check-label" for="method-cash">Dinheiro (valor integral, com troco)</label>
                </div>

                <label class="form-label" for="received_amount">Valor recebido em dinheiro (R$)</label>
                <input class="form-control @error('received_amount') is-invalid @enderror" type="text" inputmode="decimal"
                       name="received_amount" id="received_amount" value="{{ old('received_amount') }}" placeholder="30,00" autocomplete="off">
                @error('received_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Necessário apenas para pagamento em dinheiro.</div>
            </div>
            <div class="card-footer bg-transparent">
                <button type="submit" class="btn btn-primary">Confirmar pagamento simulado</button>
            </div>
        </form>
    @endif
@endsection
