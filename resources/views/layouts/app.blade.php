<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MarsOtomasyon') · MarsOtomasyon</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
@php
    $shell = app(\App\Modules\Core\Shell\ShellContext::class)->state(request());
    $navIcons = [
        'Ana Sayfa' => '⌂',
        'Cariler' => '◉',
        'Ürün/Stok' => '◈',
        'Satış' => '◴',
        'Alış' => '◉',
        'Üretim' => '⚙',
        'Fason' => '⇄',
        'Kasa/Banka' => '₺',
        'Çek/Senet' => '◇',
        'İadeler' => '↩',
        'İthalat' => '▧',
        'E-Ticaret/B2B' => '⇆',
        'İletişim' => '✉',
        'Operasyon' => '✓',
        'Raporlar' => '▥',
        'Ayarlar' => '⚙',
    ];
@endphp
<body class="app-body" data-workspace-title="@yield('title', 'MarsOtomasyon')">
<div class="app-shell">
    <aside class="app-sidebar" data-app-sidebar>
        <div class="app-brand">
            <span class="app-brand-mark">M</span>
            <div>
                <strong>MarsOtomasyon</strong>
                <small>Ön Muhasebe · Operasyon</small>
            </div>
        </div>

        <div class="sidebar-search">
            <input type="search" placeholder="Menüde Ara" aria-label="Menüde ara" data-nav-search>
            <span aria-hidden="true">⌕</span>
        </div>

        <nav class="app-navigation" aria-label="Ana menü" data-app-navigation>
            @foreach ($shell['navigation'] as $item)
                <a
                    href="{{ route($item['route']) }}"
                    class="{{ request()->routeIs($item['route']) || ($item['route'] === 'settings.index' && request()->routeIs('settings.*')) ? 'is-active' : '' }}"
                    data-workspace-link
                    data-command-item
                    data-nav-item
                    data-nav-text="{{ mb_strtolower($item['label']) }}"
                ><span class="app-nav-icon" aria-hidden="true">{{ $navIcons[$item['label']] ?? '•' }}</span><span>{{ $item['label'] }}</span></a>
            @endforeach
        </nav>

        <div class="app-sidebar-footer">
            <div class="app-sidebar-footer-row">
                <span>{{ $shell['user']?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="button-link">Çıkış</button>
                </form>
            </div>
        </div>
    </aside>

    <section class="app-main">
        <header class="app-topbar">
            <div class="app-topbar-left">
                <button type="button" class="icon-button mobile-only" data-sidebar-toggle aria-label="Menüyü aç/kapat">☰</button>
                <div class="app-crumb">@yield('title', 'MarsOtomasyon')</div>
            </div>

            <div class="app-topbar-actions">
                @if ($shell['company'] !== null)
                    <form method="GET" action="{{ route('search') }}" class="topbar-search" role="search">
                        <input
                            type="search"
                            name="q"
                            value="{{ request()->routeIs('search') ? request('q') : '' }}"
                            minlength="2"
                            maxlength="120"
                            placeholder="Global ara: kayıt, kullanıcı, rol…"
                            aria-label="Global arama"
                            data-dirty-ignore
                        >
                    </form>
                @endif

                <button type="button" class="button-secondary" data-command-open>☷ İşlemler <kbd>⌘K</kbd></button>

                <details class="topbar-menu">
                    <summary>{{ $shell['company']?->name ?? 'Firma seçilmedi' }} ▾</summary>
                    <div class="topbar-menu-panel">
                        <small>Aktif firma</small>
                        <strong>{{ $shell['company']?->name ?? 'Seçilmedi' }}</strong>

                        @if ($shell['company'] !== null)
                            <small>Aktif şube</small>
                            @if ($shell['branches']->isEmpty())
                                <strong>Aktif şube yok</strong>
                            @elseif ($shell['branches']->count() === 1 && $shell['branch'] !== null)
                                <strong>{{ $shell['branch']->code }} · {{ $shell['branch']->name }}</strong>
                            @else
                                <form method="POST" action="{{ route('context.branches.select') }}" data-branch-selector-form>
                                    @csrf
                                    <select name="branch_id" data-branch-selector aria-label="Aktif şube">
                                        <option value="">Şube seçin</option>
                                        @foreach ($shell['branches'] as $branchOption)
                                            <option value="{{ $branchOption->getKey() }}" @selected($shell['branch']?->getKey() === $branchOption->getKey())>{{ $branchOption->code }} · {{ $branchOption->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif

                            @if ($shell['companies']->count() > 1)
                                <a href="{{ route('context.companies') }}" data-workspace-link>Firma değiştir</a>
                            @endif
                        @endif
                    </div>
                </details>

                <details class="topbar-menu user-menu">
                    <summary>{{ $shell['user']?->name ?? 'Kullanıcı' }} ▾</summary>
                    <div class="topbar-menu-panel">
                        <small>Kullanıcı</small>
                        <strong>{{ $shell['user']?->name }}</strong>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="button-secondary">Çıkış Yap</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <nav class="workspace-tabs" aria-label="Çalışma sekmeleri" data-workspace-tabs></nav>

        <main class="app-content">
            @if (session('status'))
                <div class="notice-success" role="status">{{ session('status') }}</div>
            @endif
            @yield('app-content')
        </main>
    </section>
</div>

<dialog class="command-palette" data-command-palette>
    <div class="command-palette-head">
        <input type="search" placeholder="Komut ara…" aria-label="Komut ara" data-command-search>
        <button type="button" class="icon-button" data-command-close aria-label="Kapat">×</button>
    </div>
    <div class="command-palette-list" data-command-list>
        @foreach ($shell['navigation'] as $item)
            <a href="{{ route($item['route']) }}" data-command-option data-command-text="{{ mb_strtolower($item['label']) }}">{{ $item['label'] }}</a>
        @endforeach
    </div>
</dialog>
@stack('scripts')
</body>
</html>
