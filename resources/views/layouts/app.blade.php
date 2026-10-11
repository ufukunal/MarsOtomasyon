<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
@php
    $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
    $visibleNavigation = collect(config('navigation', []))
        ->map(function (array $group): array {
            $group['items'] = collect($group['items'] ?? [])
                ->filter(fn (array $item): bool => auth()->check()
                    && auth()->user()->can($item['permission'])
                    && \Illuminate\Support\Facades\Route::has($item['route']))
                ->values()
                ->all();

            return $group;
        })
        ->filter(fn (array $group): bool => $group['items'] !== [])
        ->values();
    $activeGroup = $visibleNavigation->first(
        fn (array $group): bool => collect($group['items'])
            ->contains(fn (array $item): bool => $item['route'] === $routeName),
    );
    $activeItem = $activeGroup
        ? collect($activeGroup['items'])->first(fn (array $item): bool => $item['route'] === $routeName)
        : null;
    $activeTitle = $pageTitle ?? ($activeItem['label'] ?? 'Ana Sayfa');
    $activeSection = $activeGroup['label'] ?? 'Çalışma Alanı';
    $navigationIcons = [
        'Kartlar' => '▦', 'Stok' => '◫', 'Satış' => '◴',
        'Alış' => '◈', 'Finans' => '⌁', 'İadeler' => '↩',
        'İthalat' => '◇', 'Üretim' => '⚒', 'E-Ticaret' => '▣',
        'Raporlar' => '▤', 'Ayarlar' => '⚙',
    ];
@endphp
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ url('/') }}">
            <span class="brand-mark" aria-hidden="true">M</span>
            <span class="brand-name">
                <strong>{{ config('app.name') }}</strong>
                <small>Ön Muhasebe · Operasyon</small>
            </span>
        </a>

        <div class="sidebar-search">
            <label class="sr-only" for="menu-search">Menüde ara</label>
            <input id="menu-search" type="search" placeholder="Menüde Ara" autocomplete="off">
            <span aria-hidden="true">⌕</span>
        </div>

        <nav class="navigation" aria-label="{{ __('app.navigation') }}">
            @foreach ($visibleNavigation as $group)
                @php($hasActive = collect($group['items'])->contains(fn (array $item): bool => $item['route'] === $routeName))
                <details class="nav-group" @if($hasActive) open @endif>
                    <summary class="nav-group-title">
                        <span class="nav-icon" aria-hidden="true">{{ $navigationIcons[$group['label']] ?? '◇' }}</span>
                        <span>{{ $group['label'] }}</span>
                        <span class="nav-chevron" aria-hidden="true">⌄</span>
                    </summary>
                    <div class="nav-children">
                        @foreach ($group['items'] as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                @class(['nav-link', 'is-active' => $item['route'] === $routeName])
                                @if($item['route'] === $routeName) aria-current="page" @endif
                            >{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </nav>
        <div class="sidebar-foot">MarsProject · UAT</div>
    </aside>

    <header class="topbar">
        <div class="topbar-breadcrumb">
            <span>{{ $activeSection }}</span>
            <span aria-hidden="true">›</span>
            <strong>{{ $activeTitle }}</strong>
        </div>
        <div class="topbar-context">
            @auth
                <livewire:shell.company-switcher />
                @if (class_exists(\App\Livewire\Components\PeriodSwitcher::class))
                    <livewire:components.period-switcher />
                @endif
                <span class="user-name">{{ auth()->user()->name }}</span>
            @endauth
        </div>
    </header>

    <div class="worktabs" aria-label="Aktif ekran">
        <span class="worktab-current"><span aria-hidden="true">▤</span> {{ $activeTitle }}</span>
    </div>

    <main class="content" id="main-content">
        <header class="page-header">
            <div>
                <span class="page-category">{{ $activeSection }}</span>
                <h1>{{ $activeTitle }}</h1>
                @isset($pageDescription)
                    <p>{{ $pageDescription }}</p>
                @endisset
            </div>
            @isset($pageActions)
                <div class="page-actions">{{ $pageActions }}</div>
            @endisset
        </header>

        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>
</div>

@livewireScripts
</body>
</html>
