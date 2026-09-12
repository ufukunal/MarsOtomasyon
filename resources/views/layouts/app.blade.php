<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MarsOtomasyon') · MarsOtomasyon</title>
    @vite(['resources/css/app.css', 'resources/css/search.css', 'resources/css/reference-ui.css', 'resources/css/reference-commerce.css', 'resources/js/app.js'])
    <script src="{{ asset('js/account-profile.js') }}" defer></script>
</head>
@php($shell = app(\App\Modules\Core\Shell\ShellContext::class)->state(request()))
@php($navSections = [
    'Ana Sayfa' => 'Genel',
    'Cariler' => 'Ticari',
    'Üretim' => 'Operasyon',
    'Kasa/Banka' => 'Finans',
    'E-Ticaret/B2B' => 'Kanallar',
    'Raporlar' => 'Yönetim',
])
<body class="app-body preacc-simple" data-workspace-title="@yield('title', 'MarsOtomasyon')">
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
            <div class="sidebar-search-wrap">
                <input type="search" placeholder="Menüde Ara" aria-label="Menüde ara" data-menu-search data-dirty-ignore>
            </div>
        </div>

        <nav class="app-navigation" aria-label="Ana menü" data-app-navigation>
            @foreach ($shell['navigation'] as $item)
                @if (isset($navSections[$item['label']]))
                    <div class="nav-section" data-nav-section>{{ $navSections[$item['label']] }}</div>
                @endif
                <a
                    href="{{ route($item['route']) }}"
                    class="{{ request()->routeIs($item['route']) || ($item['route'] === 'settings.index' && request()->routeIs('settings.*')) ? 'is-active' : '' }}"
                    data-workspace-link
                    data-command-item
                    data-nav-item
                    data-nav-text="{{ mb_strtolower($item['label']) }}"
                >{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="app-sidebar-footer">
            <span>{{ $shell['user']?->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="button-link">Çıkış</button>
            </form>
        </div>
    </aside>

    <section class="app-main">
        <header class="app-topbar">
            <div class="app-topbar-left">
                <button type="button" class="icon-button mobile-only" data-sidebar-toggle aria-label="Menüyü aç/kapat">☰</button>
                <div class="topbar-crumb">
                    <strong>@yield('title', 'MarsOtomasyon')</strong>
                    <span>MarsOtomasyon / Aktif çalışma alanı</span>
                </div>

                <div class="context-pill">
                    <span>Firma</span>
                    <strong>{{ $shell['company']?->name ?? 'Seçilmedi' }}</strong>
                    @if ($shell['companies']->count() > 1)
                        <a href="{{ route('context.companies') }}">Değiştir</a>
                    @endif
                </div>

                @if ($shell['company'] !== null)
                    <div class="context-pill">
                        <span>Şube</span>
                        @if ($shell['branches']->isEmpty())
                            <strong>Aktif şube yok</strong>
                        @elseif ($shell['branches']->count() === 1 && $shell['branch'] !== null)
                            <strong>{{ $shell['branch']->name }}</strong>
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
                    </div>
                @endif
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
                            placeholder="Global ara: ürün, cari, belge... (Ctrl+K)"
                            aria-label="Global arama"
                            data-dirty-ignore
                        >
                    </form>
                @endif
                <button type="button" class="button-secondary" data-command-open>Ekranlar</button>
                <button type="button" class="button-secondary">İşlemler</button>
                <div class="context-pill"><span>Kullanıcı</span><strong>{{ $shell['user']?->name }}</strong></div>
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
        <input type="search" placeholder="Ekran veya işlem ara…" aria-label="Komut ara" data-command-search>
        <button type="button" class="icon-button" data-command-close aria-label="Kapat">×</button>
    </div>
    <div class="command-palette-list" data-command-list>
        @foreach ($shell['navigation'] as $item)
            <a href="{{ route($item['route']) }}" data-command-option data-command-text="{{ mb_strtolower($item['label']) }}">{{ $item['label'] }}</a>
        @endforeach
    </div>
</dialog>
</body>
</html>
