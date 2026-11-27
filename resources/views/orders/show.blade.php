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
                <dd class="col-sm-9"><span class="badge text-bg-warning">{{ $order->status->value }}</span></dd>
            </dl>
        </div>
        <div class="card-footer bg-transparent">
            <a class="btn btn-outline-secondary" href="{{ route('menu.index') }}">Voltar ao cardápio</a>
        </div>
    </div>
@endsection
