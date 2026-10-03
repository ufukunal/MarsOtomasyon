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
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ url('/') }}">{{ config('app.name') }}</a>

        <nav class="navigation" aria-label="{{ __('app.navigation') }}">
            @foreach (config('navigation', []) as $group)
                @php
                    $visibleItems = collect($group['items'] ?? [])->filter(
                        fn (array $item): bool => auth()->check()
                            && auth()->user()->can($item['permission'])
                    );
                @endphp

                @if ($visibleItems->isNotEmpty())
                    <section class="nav-group">
                        <h2>{{ $group['label'] }}</h2>
                        @foreach ($visibleItems as $item)
                            <a
                                href="{{ \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#' }}"
                                @class(['nav-link', 'is-disabled' => ! \Illuminate\Support\Facades\Route::has($item['route'])])
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </section>
                @endif
            @endforeach
        </nav>
    </aside>

    <header class="topbar">
        <div class="topbar-search">
            <label class="sr-only" for="global-search">{{ __('app.search') }}</label>
            <input id="global-search" type="search" placeholder="{{ __('app.search_placeholder') }}" disabled>
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

    <main class="content">
        @isset($pageTitle)
            <header class="page-header">
                <div>
                    <h1>{{ $pageTitle }}</h1>
                    @isset($pageDescription)
                        <p>{{ $pageDescription }}</p>
                    @endisset
                </div>
                @isset($pageActions)
                    <div class="page-actions">{{ $pageActions }}</div>
                @endisset
            </header>
        @endisset

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
