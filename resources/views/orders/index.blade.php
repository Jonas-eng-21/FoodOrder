@extends('layouts.app')

@section('title', 'Pedidos — '.config('app.name'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">Pedidos</h1>
            <p class="fo-muted mb-0">Do mais recente para o mais antigo. Horários em UTC.</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('menu.index') }}">Novo pedido</a>
    </div>

    @if ($orders->isEmpty())
        <div class="fo-card p-4 text-center" role="status">
            <p class="h5 mb-2">Nenhum pedido foi criado até o momento.</p>
            <p class="fo-muted">Escolha um produto no cardápio para criar o primeiro pedido.</p>
            <a class="btn btn-primary" href="{{ route('menu.index') }}">Ir para o cardápio</a>
        </div>
    @else
        <div class="fo-card overflow-hidden">
            <table class="fo-table">
                <thead>
                    <tr>
                        <th scope="col">Pedido</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">Subtotal</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Criado em</th>
                        <th scope="col"><span class="visually-hidden">Ação</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td data-label="Pedido"><strong>#{{ $order->id }}</strong></td>
                            <td data-label="Descrição">{{ $order->description }}</td>
                            <td data-label="Subtotal"><span class="fo-money">{{ $order->formatted_subtotal }}</span></td>
                            <td data-label="Estado">@include('partials.status-badge', ['status' => $order->status])</td>
                            <td data-label="Criado em" class="fo-muted">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            <td class="fo-cell-action">
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('orders.show', $order) }}">Ver detalhes<span class="visually-hidden"> do pedido #{{ $order->id }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
