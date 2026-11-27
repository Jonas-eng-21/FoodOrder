@extends('layouts.app')

@section('title', 'Cardápio — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <h1 class="h2 fw-bold mb-1">Cardápio</h1>
        <p class="fo-muted mb-0">Escolha um produto para montar o pedido.</p>
    </div>

    @if ($products->isEmpty())
        <div class="alert alert-info" role="status">Nenhum produto disponível no momento.</div>
    @else
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
            @foreach ($products as $product)
                <div class="col">
                    <article class="fo-card fo-product h-100 d-flex flex-column">
                        <div class="p-3 d-flex justify-content-between align-items-center gap-3 flex-grow-1">
                            <h2 class="h5 fw-bold mb-0">{{ $product->name }}</h2>
                            <span class="fo-money fo-price-tag">{{ $product->formatted_price }}</span>
                        </div>
                        <div class="p-3 pt-0">
                            <a class="btn btn-primary w-100" href="{{ route('orders.create', ['product_id' => $product->id]) }}">Fazer pedido</a>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif
@endsection
