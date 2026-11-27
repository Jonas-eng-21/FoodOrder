<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/foodorder.css') }}">
</head>
<body>
    <a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#conteudo">Ir para o conteúdo</a>

    <header class="fo-header mb-4">
        <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
            <a class="fo-brand" href="{{ route('menu.index') }}">Food<span>Order</span></a>
            <nav aria-label="Navegação principal">
                <ul class="fo-nav">
                    <li><a href="{{ route('menu.index') }}" @if (request()->routeIs('menu.index', 'orders.create')) aria-current="page" @endif>Cardápio</a></li>
                    <li><a href="{{ route('orders.index') }}" @if (request()->routeIs('orders.index', 'orders.show')) aria-current="page" @endif>Pedidos</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container pb-5" id="conteudo">
        @include('partials.alerts')

        @yield('content')
    </main>
</body>
</html>
