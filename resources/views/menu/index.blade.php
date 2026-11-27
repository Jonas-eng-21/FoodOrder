@extends('layouts.app')

@section('title', 'Cardápio — '.config('app.name'))

@section('content')
    <h1 class="h3 mb-4">Cardápio</h1>

    @forelse ($products as $product)
        @if ($loop->first)
            <div class="row row-cols-1 row-cols-md-3 g-3">
        @endif
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <h2 class="h5 card-title mb-0">{{ $product->name }}</h2>
                    <span class="fs-5 fw-semibold">{{ $product->formatted_price }}</span>
                </div>
                <div class="card-footer bg-transparent">
                    <a class="btn btn-primary w-100" href="{{ route('orders.create', ['product_id' => $product->id]) }}">Fazer pedido</a>
                </div>
            </div>
        </div>
        @if ($loop->last)
            </div>
        @endif
    @empty
        <div class="alert alert-info" role="status">Nenhum produto disponível no momento.</div>
    @endforelse
@endsection
