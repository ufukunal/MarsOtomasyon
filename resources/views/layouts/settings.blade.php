<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ayarlar') · MarsOtomasyon</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="settings-shell">
    <header class="settings-header">
        <div>
            <div class="eyebrow">MarsOtomasyon</div>
            <h1 class="settings-title">@yield('heading', 'Ayarlar')</h1>
        </div>
        <nav class="settings-nav" aria-label="Ayarlar">
            @can('core.user.view')
                <a href="{{ route('settings.users.index') }}">Kullanıcılar</a>
            @endcan
            @can('core.role.view')
                <a href="{{ route('settings.roles.index') }}">Roller ve Yetkiler</a>
            @endcan
            <a href="{{ route('home') }}">Ana Sayfa</a>
        </nav>
    </header>

    @if (session('status'))
        <div class="notice-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice-error" role="alert">
            <strong>İşlem tamamlanamadı.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <main class="settings-content">
        @yield('content')
    </main>
</div>
</body>
</html>
