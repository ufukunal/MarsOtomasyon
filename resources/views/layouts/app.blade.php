<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/css/v65-shell.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
@php
    $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
    $visibleNavigation = collect(config('navigation', []))
        ->map(function (array $group): array {
            $group['items'] = collect($group['items'] ?? [])
                ->filter(fn (array $item): bool => auth()->check()
                    && ($item['permission'] === null || auth()->user()->can($item['permission']))
                    && (! isset($item['permission_secondary']) || auth()->user()->can($item['permission_secondary']))
                    && \Illuminate\Support\Facades\Route::has($item['route']))
                ->values()
                ->all();

            return $group;
        })
        ->filter(fn (array $group): bool => $group['items'] !== [])
        ->values();
    // Edit/detail routes are not separate sidebar links. Resolve them to their
    // existing authorized module, without adding fake navigation destinations.
    $routeParts = explode('.', (string) $routeName);
    $routeFamily = $routeParts[0] ?? '';
    $activeGroup = $visibleNavigation->first(
        fn (array $group): bool => collect($group['items'])
            ->contains(fn (array $item): bool => $item['route'] === $routeName),
    );
    $activeGroup ??= $visibleNavigation->first(
        fn (array $group): bool => collect($group['items'])
            ->contains(fn (array $item): bool => explode('.', $item['route'])[0] === $routeFamily),
    );
    $activeItem = $activeGroup
        ? collect($activeGroup['items'])->first(fn (array $item): bool => $item['route'] === $routeName)
        : null;
    if ($activeItem === null && $activeGroup) {
        $routeSection = $routeParts[1] ?? '';
        $activeItem = collect($activeGroup['items'])->first(
            fn (array $item): bool => $routeSection !== ''
                && str_starts_with($item['route'], $routeFamily.'.'.$routeSection.'.'),
        ) ?? collect($activeGroup['items'])->first(
            fn (array $item): bool => explode('.', $item['route'])[0] === $routeFamily,
        );
    }
    $activeTitle = $pageTitle ?? ($activeItem['label'] ?? 'Ana Sayfa');
    $activeSection = $activeGroup['label'] ?? 'Çalışma Alanı';
    $navigationIcons = [
        'Ana Sayfa' => '⌂', 'Cariler' => '◉',
        'Ürün / Stok' => '▦', 'Satış' => '◴',
        'Alış' => '◉', 'Stok ve Depo' => '▦',
        'Kasa / Banka' => '₺', 'Çek / Senet' => '▤', 'İade / RMA' => '↩',
        'Ambar' => '▣', 'İthalat' => '◇', 'Üretim' => '⚒', 'E-Ticaret' => '▣',
        'Raporlar' => '▤', 'Ayarlar' => '⚙',
    ];
@endphp
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ url('/') }}">
            <span class="brand-mark" aria-hidden="true">M</span>
            <span class="brand-name">
                <strong>MarsOtomasyon</strong>
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
                @php
                    $hasActive = $activeGroup && $activeGroup['label'] === $group['label'];
                @endphp
                <details class="nav-group" @if($hasActive) open @endif>
                    <summary class="nav-group-title">
                        <span class="nav-icon" aria-hidden="true">{{ $navigationIcons[$group['label']] ?? '◇' }}</span>
                        <span>{{ $group['label'] }}</span>
                        <span class="nav-chevron" aria-hidden="true">⌄</span>
                    </summary>
                    <div class="nav-children">
                        @if($group['label'] === 'Ürün / Stok')
                            @php
                                $stockSections = [
                                    'Ürünler ve Fiyatlar' => ['products.index', 'price-lists.index', 'categories.index', 'brands.index', 'variant-groups.detail', 'units.index'],
                                    'Stok ve Depolar' => ['stock.status', 'stock.movements', 'locations.index', 'stock.transfers.index', 'stock.transfers.create', 'stock.reservations.index', 'stock.quarantine.index'],
                                    'Stok Sayımları' => ['stock.counts.index', 'stock.counts.create', 'stock.counts.quick', 'stock.counts.results'],
                                    'Stok İşlemleri' => ['stock.warehouse-slips.index', 'stock.costs', 'stock.opening.index', 'imports.index'],
                                ];
                            @endphp
                            @foreach($stockSections as $sectionLabel => $sectionRoutes)
                                @php
                                    $sectionItems = collect($group['items'])->filter(fn (array $item): bool => in_array($item['route'], $sectionRoutes, true));
                                    $sectionActive = $activeItem && in_array($activeItem['route'], $sectionRoutes, true);
                                @endphp
                                @if($sectionItems->isNotEmpty())
                                    <details class="nav-subgroup" @if($sectionActive || $loop->first) open @endif>
                                        <summary class="nav-subgroup-title">{{ $sectionLabel }} <span aria-hidden="true">⌄</span></summary>
                                        <div class="nav-subgroup-links">
                                            @foreach($sectionItems as $item)
                                                <a href="{{ route($item['route']) }}"
                                                    @class(['nav-link', 'is-active' => $activeItem && $item['route'] === $activeItem['route']])
                                                    @if($activeItem && $item['route'] === $activeItem['route']) aria-current="page" @endif
                                                ><span class="nav-child-dot" aria-hidden="true">◦</span>{{ $item['label'] }}</a>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            @endforeach
                        @else
                            @foreach ($group['items'] as $item)
                                <a href="{{ route($item['route']) }}"
                                    @class(['nav-link', 'is-active' => $activeItem && $item['route'] === $activeItem['route']])
                                    @if($activeItem && $item['route'] === $activeItem['route']) aria-current="page" @endif
                                ><span class="nav-child-dot" aria-hidden="true">◦</span>{{ $item['label'] }}</a>
                            @endforeach
                        @endif
                    </div>
                </details>
            @endforeach
        </nav>
        <div class="sidebar-foot">MarsOtomasyon · Ön Muhasebe</div>
    </aside>

    <header class="topbar">
        @canany(['sales_invoices.create', 'collections.create', 'products.create', 'contacts.create'])
            <details class="topbar-create">
                <summary>＋ Yeni İşlem</summary>
                <div class="topbar-create-menu">
                    @can('sales_invoices.create')
                        <a href="{{ route('sales.invoices.edit') }}">Satış Faturası</a>
                    @endcan
                    @can('collections.create')
                        <a href="{{ route('finance.collections.create') }}">Tahsilat</a>
                    @endcan
                    @can('products.create')
                        <a href="{{ route('products.create') }}">Ürün</a>
                    @endcan
                    @can('contacts.create')
                        <a href="{{ route('contacts.create') }}">Cari</a>
                    @endcan
                </div>
            </details>
        @endcanany
        <div class="topbar-breadcrumb">
            <strong>{{ $activeTitle }}</strong>
        </div>
        <div class="topbar-search" role="search">
            <label class="sr-only" for="global-screen-search">Ekranlarda ara</label>
            <input id="global-screen-search" type="search" autocomplete="off" placeholder="Global ara: ekran, ürün, cari… (Ctrl+K)" aria-controls="menu-search">
            <span aria-hidden="true">⌕</span>
        </div>
        <div class="topbar-context">
            @auth
                <livewire:shell.company-switcher />
                @if (class_exists(\App\Livewire\Components\PeriodSwitcher::class))
                    <livewire:components.period-switcher />
                @endif
                <details class="user-menu">
                    <summary aria-label="Kullanıcı menüsü">{{ auth()->user()->name }} <span aria-hidden="true">⌄</span></summary>
                    <div class="user-menu-panel">
                        <span class="user-menu-caption">{{ auth()->user()->email }}</span>
                        <a href="{{ route('home') }}">Ana Sayfa</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="button-secondary">Çıkış Yap</button>
                        </form>
                    </div>
                </details>
            @endauth
        </div>
    </header>

    <nav class="worktabs" aria-label="Açık ekranlar"
        data-worktabs
        data-tab-scope="{{ auth()->id() }}.{{ session('active_company_id') }}"
        data-active-path="{{ request()->path() }}"
        data-active-title="{{ $activeTitle }}">
        <a class="worktab-current" href="{{ url()->current() }}" aria-current="page"><span aria-hidden="true">▤</span> {{ $activeTitle }}</a>
    </nav>

    <main class="content" id="main-content">
        <header class="page-header">
            <div>
                <span class="page-category sr-only">{{ $activeSection }}</span>
                <h1><span class="page-title-icon" aria-hidden="true">{{ $navigationIcons[$activeSection] ?? '⌂' }}</span>{{ $activeTitle }}</h1>
                @isset($pageDescription)
                    <p>{{ $pageDescription }}</p>
                @endisset
            </div>
            <div class="page-actions v65-page-actions" aria-label="Sayfa işlemleri">
                @if ($routeName === 'warehouse.transport.index')
                    @can('warehouse_slips.create')
                        <a class="button-link" href="{{ route('warehouse.transport.create') }}">+ Yeni Ambar Fişi</a>
                    @endcan
                @endif
                @if ($routeName === 'products.show')
                    @canany(['supplier_invoices.create', 'purchase_orders.create'])
                        <details class="page-action-menu">
                            <summary>Alım İşlemleri <span aria-hidden="true">⌄</span></summary>
                            <div class="page-action-menu-items">
                                @can('purchase_orders.create')<a href="{{ route('purchases.orders.edit') }}">Satınalma Siparişi</a>@endcan
                                @can('supplier_invoices.create')<a href="{{ route('purchases.invoices.edit') }}">Alış Faturası</a>@endcan
                            </div>
                        </details>
                    @endcanany
                    @canany(['quotes.create', 'sales_orders.create', 'sales_invoices.create'])
                        <details class="page-action-menu">
                            <summary>Satış İşlemleri <span aria-hidden="true">⌄</span></summary>
                            <div class="page-action-menu-items">
                                @can('quotes.create')<a href="{{ route('sales.quotes.edit') }}">Teklif</a>@endcan
                                @can('sales_orders.create')<a href="{{ route('sales.orders.edit') }}">Satış Siparişi</a>@endcan
                                @can('sales_invoices.create')<a href="{{ route('sales.invoices.edit') }}">Satış Faturası</a>@endcan
                            </div>
                        </details>
                    @endcanany
                @endif

                @if ($routeName === 'products.show')
                    <details class="page-action-menu page-action-more">
                        <summary>Diğer <span aria-hidden="true">⌄</span></summary>
                        <div class="page-action-menu-items">
                            @can('returns.view')
                                <a href="{{ route('returns.center') }}">İade İşlemleri</a>
                            @endcan
                            @can('products.update')
                                <a href="{{ route('products.edit', ['product' => request()->route('product')]) }}">Düzenle</a>
                            @endcan
                            <button type="button" onclick="window.print()" title="Yazdırma penceresinde PDF olarak kaydedebilirsiniz">Yazdır / PDF</button>
                            <button type="button" onclick="if (history.length > 1) history.back(); else location.href='{{ route('home') }}'">Geri</button>
                        </div>
                    </details>
                @elseif ($routeName === 'products.index')
                    {{-- Product-specific create, print and export actions are teleported here. --}}
                @elseif ($routeName === 'warehouse.transport.show')
                    @can('warehouse_slips.view')
                        <a class="button-secondary" target="_blank" rel="noopener" href="{{ route('warehouse.transport.print', ['slip' => request()->route('id'), 'format' => 'slip', 'autoprint' => 1]) }}" title="Yazdırma penceresinde PDF olarak kaydedebilirsiniz">Yazdır / PDF</a>
                    @endcan
                @elseif (in_array($routeName, ['warehouse.transport.create', 'warehouse.transport.edit'], true))
                    <button type="button" class="button-secondary" onclick="document.querySelector('[data-wsl-print-slip]')?.click()" title="Yazdırma penceresinde PDF olarak kaydedebilirsiniz">Yazdır / PDF</button>
                @else
                    <button type="button" class="button-secondary" onclick="window.print()" title="Yazdırma penceresinde PDF olarak kaydedebilirsiniz">Yazdır / PDF</button>
                @endif
                @if (in_array($routeName, ['warehouse.transport.index', 'warehouse.transport.show', 'warehouse.transport.create', 'warehouse.transport.edit'], true))
                    @can('warehouse_slips.view')
                        <a class="button-secondary" href="{{ route('warehouse.transport.export', $routeName === 'warehouse.transport.show' ? ['id' => request()->route('id')] : []) }}" title="Kayıtlı ambar fişlerini Excel uyumlu CSV olarak dışa aktar">Excel</a>
                    @endcan
                @endif
                @isset($pageActions){{ $pageActions }}@endisset
                @unless ($routeName === 'products.show')
                    <button type="button" class="button-secondary" onclick="if (history.length > 1) history.back(); else location.href='{{ route('home') }}'">Geri</button>
                @endunless
            </div>
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
