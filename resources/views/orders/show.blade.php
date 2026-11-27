@extends('layouts.app')

@section('title', 'Pedido #'.$order->id.' — '.config('app.name'))

@section('content')
    @php($state = $order->state())
    @php($payment = $order->payment)

    <a class="d-inline-block mb-3" href="{{ route('orders.index') }}">&larr; Voltar à lista de pedidos</a>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">Pedido #{{ $order->id }}</h1>
            <p class="fo-muted mb-0">Criado em {{ $order->created_at->format('d/m/Y H:i') }} (UTC)</p>
        </div>
        <div class="fs-6">@include('partials.status-badge', ['status' => $order->status])</div>
    </div>

    @if ($state->canStart() || $state->canReady() || $state->canDeliver() || $state->canCancel())
        <section class="fo-card p-3 mb-4" aria-labelledby="acoes-titulo">
            <h2 class="fo-card-title mb-3" id="acoes-titulo">Ações do pedido</h2>
            <div class="d-flex flex-wrap gap-2">
                @foreach ([
                    ['start', $state->canStart(), 'Iniciar preparo', 'btn-primary'],
                    ['ready', $state->canReady(), 'Finalizar preparo', 'btn-primary'],
                    ['deliver', $state->canDeliver(), 'Entregar pedido', 'btn-success'],
                    ['cancel', $state->canCancel(), 'Cancelar pedido', 'btn-outline-danger'],
                ] as [$action, $allowed, $label, $class])
                    @if ($allowed)
                        <form method="POST" action="{{ route('orders.'.$action, $order) }}">
                            @csrf
                            <button type="submit" class="btn {{ $class }}">{{ $label }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="fo-card p-3 p-md-4 h-100" aria-labelledby="composicao-titulo">
                <h2 class="fo-card-title mb-3" id="composicao-titulo">Composição do pedido</h2>
                <dl class="fo-rows">
                    <div><dt>{{ $order->product_name_snapshot }}</dt><dd class="fo-money">{{ \App\Support\Money::format($order->base_price_cents) }}</dd></div>
                    @foreach ($order->extras_snapshot as $extra)
                        <div><dt>+ {{ $extra['name'] }}</dt><dd class="fo-money">{{ \App\Support\Money::format($extra['price_cents']) }}</dd></div>
                    @endforeach
                    <div class="fo-total"><dt>Subtotal</dt><dd class="fo-money">{{ $order->formatted_subtotal }}</dd></div>
                </dl>
                <p class="fo-muted small mt-3 mb-0">Descrição: {{ $order->description }}</p>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="fo-card p-3 p-md-4 h-100" aria-labelledby="pagamento-titulo">
                <h2 class="fo-card-title mb-3" id="pagamento-titulo">Pagamento</h2>

                @if ($payment)
                    <dl class="fo-rows">
                        <div><dt>Forma de pagamento</dt><dd>{{ $payment->method->label() }}</dd></div>
                        <div><dt>Subtotal original</dt><dd class="fo-money">{{ $order->formatted_subtotal }}</dd></div>
                        @if ($payment->total_cents < $order->subtotal_cents)
                            <div><dt>Desconto</dt><dd class="fo-money">&minus; {{ \App\Support\Money::format($order->subtotal_cents - $payment->total_cents) }}</dd></div>
                        @endif
                        <div class="fo-total"><dt>Total pago</dt><dd class="fo-money">{{ \App\Support\Money::format($payment->total_cents) }}</dd></div>
                        @if ($payment->received_cents !== null)
                            <div><dt>Valor recebido</dt><dd class="fo-money">{{ \App\Support\Money::format($payment->received_cents) }}</dd></div>
                            <div><dt>Troco</dt><dd class="fo-money">{{ \App\Support\Money::format($payment->change_cents ?? 0) }}</dd></div>
                        @endif
                        <div class="fo-ref-row"><dt>Referência</dt><dd><code class="fo-reference">{{ $payment->simulated_reference }}</code></dd></div>
                    </dl>
                    <p class="fo-muted small mt-3 mb-0">Pagamento exclusivamente simulado: nenhuma cobrança real foi realizada.</p>
                @elseif ($state->canPay())
                    @include('orders._payment-form')
                @elseif ($order->status === \App\Enums\OrderStatus::Cancelled)
                    <p class="mb-0">Pedido cancelado antes do pagamento. Nenhuma cobrança foi realizada.</p>
                @else
                    <p class="mb-0">Nenhum pagamento registrado para este pedido.</p>
                @endif
            </section>
        </div>
    </div>

    <div class="row g-4 fo-section">
        <div class="col-lg-6">
            <section class="fo-card p-3 p-md-4 h-100" aria-labelledby="historico-titulo">
                <h2 class="fo-card-title mb-3" id="historico-titulo">Histórico do pedido</h2>
                @forelse ($order->histories as $history)
                    @if ($loop->first)<ol class="fo-timeline">@endif
                        <li>
                            <strong>{{ $history->previous_status->label() }} → {{ $history->new_status->label() }}</strong>
                            <div class="fo-muted small">{{ $history->created_at->format('d/m/Y H:i:s') }}</div>
                        </li>
                    @if ($loop->last)</ol>@endif
                @empty
                    <p class="fo-muted mb-0">Nenhuma transição registrada para este pedido.</p>
                @endforelse
            </section>
        </div>

        <div class="col-lg-6">
            <section class="fo-card p-3 p-md-4 h-100" aria-labelledby="notificacoes-titulo">
                <h2 class="fo-card-title mb-3" id="notificacoes-titulo">Notificações internas</h2>
                @if ($order->notifications->isEmpty())
                    <p class="fo-muted mb-0">Nenhuma notificação interna registrada.</p>
                @else
                    @foreach ([['Cliente', $customerNotifications, 'fo-customer', 'Nenhuma notificação para o cliente.'], ['Cozinha', $kitchenNotifications, 'fo-kitchen', 'Nenhuma notificação para a cozinha.']] as [$title, $notices, $class, $empty])
                        <h3 class="h6 fw-bold mt-3">{{ $title }}</h3>
                        @if ($notices->isEmpty())
                            <p class="fo-muted small">{{ $empty }}</p>
                        @else
                            <ul class="fo-notices {{ $class }}">
                                @foreach ($notices as $notice)
                                    <li>{{ $notice->message }}<div class="fo-muted small">{{ $notice->created_at->format('d/m/Y H:i:s') }}</div></li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                @endif
                <p class="fo-muted small mt-3 mb-0">Avisos internos da aplicação: nada é enviado a clientes ou funcionários.</p>
            </section>
        </div>
    </div>
@endsection
