<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mars B2B')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="workspace-shell">
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Mars B2B / Bayi Portalı</p>
            <h1>@yield('heading', 'Bayi Portalı')</h1>
        </div>
        <form method="POST" action="{{ route('b2b.logout') }}">@csrf<button type="submit">Çıkış</button></form>
    </section>
    <nav class="page-actions" aria-label="B2B menüsü">
        <a href="{{ route('b2b.home') }}">Hesabım</a>
        <a href="{{ route('b2b.catalog.index') }}">Katalog</a>
        <a href="{{ route('b2b.cart.index') }}">Sepet</a>
        <a href="{{ route('b2b.orders.index') }}">Siparişler</a>
        <a href="{{ route('b2b.invoices.index') }}">Faturalar</a>
        <a href="{{ route('b2b.statement') }}">Ekstre</a>
    </nav>
    @if(session('status'))<div class="notice-success" role="status">{{ session('status') }}</div>@endif
    @if(session('warning'))<div class="notice-error" role="alert">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="notice-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</body>
</html>
