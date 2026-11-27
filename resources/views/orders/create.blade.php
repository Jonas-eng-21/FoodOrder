@extends('layouts.app')

@section('title', 'Novo pedido — '.config('app.name'))

@section('content')
    <h1 class="h3 mb-4">Novo pedido</h1>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('orders.store') }}" class="card shadow-sm">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">{{ $product->name }}</h2>
                <span class="fs-5 fw-semibold">{{ $product->formatted_price }}</span>
            </div>

            <h3 class="h6">Complementos</h3>
            @forelse ($extras as $extra)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="extras[]" value="{{ $extra->code }}"
                           id="extra-{{ $extra->code }}" @checked(in_array($extra->code, (array) old('extras', []), true))>
                    <label class="form-check-label" for="extra-{{ $extra->code }}">
                        {{ $extra->name }} — {{ \App\Support\Money::format($extra->priceCents) }}
                    </label>
                </div>
            @empty
                <p class="text-body-secondary mb-0">Este produto não possui complementos.</p>
            @endforelse
        </div>

        <div class="card-footer bg-transparent d-flex gap-2">
            <button type="submit" class="btn btn-primary">Confirmar pedido</button>
            <a class="btn btn-outline-secondary" href="{{ route('menu.index') }}">Voltar ao cardápio</a>
        </div>
    </form>
@endsection
