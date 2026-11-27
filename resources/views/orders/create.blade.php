@extends('layouts.app')

@section('title', 'Novo pedido — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <h1 class="h2 fw-bold mb-1">Novo pedido</h1>
        <p class="fo-muted mb-0">Escolha os complementos desejados e confirme.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="fw-bold mb-1">Não foi possível criar o pedido:</p>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('orders.store') }}" class="fo-card" id="order-form">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        <div class="p-3 p-md-4 d-flex justify-content-between align-items-center gap-3 border-bottom">
            <h2 class="h4 fw-bold mb-0">{{ $product->name }}</h2>
            <span class="fo-money fo-price-tag" id="base-price" data-cents="{{ $product->base_price_cents }}">{{ $product->formatted_price }}</span>
        </div>

        <fieldset class="p-3 p-md-4 border-0">
            <legend class="h6 fw-bold">Complementos</legend>
            @forelse ($extras as $extra)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="extras[]" value="{{ $extra->code }}"
                           id="extra-{{ $extra->code }}" data-cents="{{ $extra->priceCents }}"
                           @checked(in_array($extra->code, (array) old('extras', []), true))>
                    <label class="form-check-label d-flex justify-content-between gap-3" for="extra-{{ $extra->code }}">
                        <span>{{ $extra->name }}</span>
                        <span class="fo-money">+ {{ \App\Support\Money::format($extra->priceCents) }}</span>
                    </label>
                </div>
            @empty
                <p class="fo-muted mb-0">Este produto não possui complementos.</p>
            @endforelse
        </fieldset>

        <div class="p-3 p-md-4 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
            <p class="mb-0" id="preview" hidden>Subtotal estimado: <span class="fo-money" id="preview-total"></span> <span class="fo-muted small">(o valor final é calculado pelo servidor)</span></p>
            <div class="d-flex flex-wrap gap-2 ms-md-auto">
                <a class="btn btn-outline-secondary" href="{{ route('menu.index') }}">Voltar ao cardápio</a>
                <button type="submit" class="btn btn-primary">Confirmar pedido</button>
            </div>
        </div>
    </form>

    <script>
        // Optional visual preview in integer cents; the server always recalculates the real subtotal.
        (function () {
            var form = document.getElementById('order-form');
            var preview = document.getElementById('preview');
            if (!form || !preview) { return; }
            var base = Number(document.getElementById('base-price').dataset.cents);
            var boxes = form.querySelectorAll('input[name="extras[]"]');
            function reais(cents) {
                return 'R$ ' + Math.floor(cents / 100).toLocaleString('pt-BR') + ',' + String(cents % 100).padStart(2, '0');
            }
            function update() {
                var total = base;
                boxes.forEach(function (box) { if (box.checked) { total += Number(box.dataset.cents); } });
                document.getElementById('preview-total').textContent = reais(total);
                preview.hidden = false;
            }
            boxes.forEach(function (box) { box.addEventListener('change', update); });
            update();
        })();
    </script>
@endsection
