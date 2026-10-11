<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        /* Approved v65 composition: shared shell, product listing and dashboard.
           The application continues to use only one compiled CSS asset. */
        .app-shell{--side:232px;--top:52px;--tabs:39px;background:#f4f7fc}
        .sidebar{background:#fff;color:#334155;border-right:1px solid #dee6f2}
        .brand{border-bottom:1px solid #e3eaf3}
        .nav-group{margin:2px 8px}
        .nav-group-title{font-size:12px;font-weight:600;min-height:39px;padding:0 10px}
        .nav-group[open]>.nav-group-title{background:#e9f0ff;color:#1964c5}
        .nav-children{padding:3px 0 6px 24px}
        .nav-link{display:flex;align-items:center;gap:7px;font-size:11px;padding:7px 8px}
        .nav-child-dot{font-size:13px;color:#8196af}
        .nav-link.is-active{border-radius:0;border-left:3px solid #3bc0df;background:#e9f0ff;color:#1468c8}
        .topbar{box-shadow:none;border-bottom:1px solid #e0e8f0}
        .page-header{min-height:51px;padding-top:8px;padding-bottom:8px}
        .page-header h1{font-size:19px}
        .v65-page-actions{display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-left:auto}
        .v65-page-actions .button-secondary{min-height:29px;font-size:11px}
        .page-action-menu{position:relative}
        .page-action-menu>summary{cursor:pointer;list-style:none;border:1px solid #dce6f1;border-radius:5px;background:#fff;padding:7px 9px;font-size:11px}
        .page-action-menu-items{position:absolute;right:0;top:100%;min-width:165px;z-index:55;display:grid;background:#fff;border:1px solid #dce6f1;box-shadow:0 7px 20px #18293622}
        .page-action-menu-items>a{padding:10px}
        .page-action-menu-items>a:hover{background:#e8f1fc}
        .v65-products{background:transparent;border:0}
        .v65-products-hint{border-color:#d5e2fc;color:#476493;background:#f9fbff;border-radius:10px;margin:0 0 10px}
        .v65-products-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff;border:1px solid #dce5f0;border-radius:10px;padding:10px 12px;margin-bottom:10px}
        .v65-products-heading .stack{gap:4px}
        .v65-products-heading small{color:#8492a6}
        .v65-products .table-toolbar{border:1px solid #dce5f0;padding:10px;background:#fff;margin-bottom:10px;border-radius:0}
        .v65-products .table-toolbar-main{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;width:auto;flex:1}
        .v65-products .table-search{min-width:0;max-width:none;flex:auto}
        .v65-products .table-filter select{width:100%;min-width:0}
        .v65-products-filter{min-width:80px}
        .v65-products-table-title{display:flex;justify-content:space-between;background:#fff;border:1px solid #dce5f0;border-bottom:0;padding:12px 14px;font-size:12px}
        .v65-products-table-title span{color:#7b8ca5}
        .v65-products .table-scroll{background:#fff;border-color:#dce5f0}
        .v65-products .data-table th{font-size:11px}
        .v65-products .data-table td{height:40px}
        .v65-products .data-table tbody tr:nth-child(even){background:#f8fafc}
        .v65-products .table-footer{background:#fff;border:1px solid #dce5f0;border-top:0}
        .v65-home-daily{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
        .v65-home-workbench{display:grid;grid-template-columns:minmax(0,3fr) minmax(220px,1fr);gap:12px}
        .v65-home-workbench .panel{min-width:0;border:1px solid #dce5f0}
        .v65-home-panelhead{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:12px}
        .v65-home-panelhead .stack{gap:3px}
        .v65-home-panelhead small{color:#7b8ca5}
        .v65-home-status{display:grid;gap:12px;align-content:start;border-top:3px solid #229cde}
        .v65-home-status a{display:block;padding:8px 0;border-bottom:1px solid #eef2f6;color:#2369bb}
        .v65-home-reports-title{font-size:12px;color:#506c8b;margin:3px 0 -3px}
        @media(max-width:980px){.v65-products .table-toolbar-main{grid-template-columns:repeat(2,minmax(0,1fr))}.v65-home-workbench{grid-template-columns:1fr}}
        @media(max-width:600px){.v65-products .table-toolbar-main{grid-template-columns:1fr}.v65-page-actions{justify-content:flex-start}}
        @media print{.sidebar,.topbar,.worktabs,.v65-page-actions,.topbar-create,.page-action-menu{display:none}.app-shell{display:block}.content{padding:0}.page-header{margin:0}.table-scroll{overflow:visible}}
    </style>
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
                            ><span class="nav-child-dot" aria-hidden="true">◦</span>{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </nav>
        <div class="sidebar-foot">{{ config('app.name') }} · Ön Muhasebe</div>
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
            <input id="global-screen-search" type="search" autocomplete="off" placeholder="Ekran ara… (Ctrl+K)" aria-controls="menu-search">
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
                    @can('returns.view')
                        <a class="button-secondary" href="{{ route('returns.center') }}">İade İşlemleri</a>
                    @endcan
                    @can('products.update')
                        <a class="button-secondary" href="{{ route('products.edit', ['product' => request()->route('product')]) }}">Düzenle</a>
                    @endcan
                @endif
                <button type="button" class="button-secondary" onclick="window.print()">Yazdır</button>
                <button type="button" class="button-secondary" onclick="window.print()" title="PDF için yazdırma ekranında PDF olarak kaydet seçin">PDF</button>
                @isset($pageActions){{ $pageActions }}@endisset
                <button type="button" class="button-secondary" onclick="if (history.length > 1) history.back(); else location.href='{{ route('home') }}'">Geri</button>
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
