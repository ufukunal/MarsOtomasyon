<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MarsOtomasyon') · MarsOtomasyon</title>
    @vite(['resources/css/app.css', 'resources/css/search.css', 'resources/js/app.js'])
</head>
@php($shell = app(\App\Modules\Core\Shell\ShellContext::class)->state(request()))
<body class="app-body" data-workspace-title="@yield('title', 'MarsOtomasyon')">
<div class="app-shell">
    <aside class="app-sidebar" data-app-sidebar>
        <div class="app-brand">
            <span class="app-brand-mark">M</span>
            <div>
                <strong>MarsOtomasyon</strong>
                <small>Ön Muhasebe ve Operasyon</small>
            </div>
        </div>

        <nav class="app-navigation" aria-label="Ana menü">
            @foreach ($shell['navigation'] as $item)
                <a
                    href="{{ route($item['route']) }}"
                    class="{{ request()->routeIs($item['route']) || ($item['route'] === 'settings.index' && request()->routeIs('settings.*')) ? 'is-active' : '' }}"
                    data-workspace-link
                    data-command-item
                >{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="app-sidebar-footer">
            <span>{{ $shell['user']?->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="button-link">Çıkış Yap</button>
            </form>
        </div>
    </aside>

    <section class="app-main">
        <header class="app-topbar">
            <div class="app-topbar-left">
                <button type="button" class="icon-button mobile-only" data-sidebar-toggle aria-label="Menüyü aç/kapat">☰</button>
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
                            placeholder="Ara…"
                            aria-label="Global arama"
                            data-dirty-ignore
                        >
                    </form>
                @endif
                <button type="button" class="button-secondary" data-command-open>Komutlar <kbd>⌘K</kbd></button>
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
</body>
</html>
