<div class="stack v65-home">
    <section class="v65-home-welcome" aria-label="Hızlı başlangıç">
        <div class="v65-home-intro">
            <strong>Bugün ne yapmak istiyorsun?</strong>
            <small>En sık kullanılan işlemler burada. Diğer işlemler soldaki menüde.</small>
        </div>
        <div class="v65-home-context">
            @if($companyName)
                <span class="v65-home-badge v65-home-badge-company">Firma: {{ $companyName }}</span>
            @endif
            <span @class(['v65-home-badge', 'is-open' => $periodOpen])>{{ $periodOpen ? 'Dönem Açık' : 'Dönem Salt Okunur' }}</span>
        </div>
    </section>

    @if($quickActions !== [])
        <nav class="v65-home-quick-grid" aria-label="Hızlı işlemler">
            @foreach($quickActions as $action)
                <a class="v65-home-quick" href="{{ route($action['route']) }}">
                    <span class="v65-home-quick-icon" aria-hidden="true">{{ $action['icon'] }}</span>
                    <strong>{{ $action['title'] }}</strong>
                    <small>{{ $action['description'] }}</small>
                </a>
            @endforeach
        </nav>
    @endif

    @if($daily !== [])
        <section class="dashboard-grid v65-home-kpis v65-home-daily" aria-label="Bugünün gerçek işlem tutarları">
            @if(isset($daily['sales']))
                <div class="v65-home-kpi">
                    <span>Bugünkü Satış</span>
                    <strong>{{ $daily['sales']['value'] }}</strong>
                    <small>{{ $daily['sales']['count'] }} kesinleşmiş TRY satış faturası</small>
                </div>
            @endif
            @if(isset($daily['collections']))
                <div class="v65-home-kpi">
                    <span>Bugünkü Tahsilat</span>
                    <strong>{{ $daily['collections']['value'] }}</strong>
                    <small>{{ $daily['collections']['count'] }} kesinleşmiş TRY tahsilat</small>
                </div>
            @endif
        </section>
    @endif

    <div class="v65-home-workbench">
        <section class="panel v65-home-tasks">
            <header class="v65-home-panelhead">
                <div class="stack">
                    <strong>Bugün Yapılacaklar</strong>
                    <small>Bugün veya öncesinde vadesi gelen taslak satış / satınalma siparişleri</small>
                </div>
                @can('sales_orders.view')<a class="button-secondary" href="{{ route('sales.orders.index') }}">Tümünü Gör</a>@endcan
            </header>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>İş</th><th>Cari / Belge</th><th>Tutar</th><th>Termin</th><th>Durum</th></tr></thead>
                    <tbody>
                    @forelse($tasks as $task)
                        <tr>
                            <td>{{ $task->document_type === \App\Enums\DocumentType::SalesOrder ? 'Satış siparişi kontrolü' : 'Satınalma siparişi kontrolü' }}</td>
                            <td>{{ $task->contact?->title ?? '—' }} ·
                                <a href="{{ $task->document_type === \App\Enums\DocumentType::SalesOrder ? route('sales.orders.edit', ['id' => $task->id]) : route('purchases.orders.edit', ['id' => $task->id]) }}">{{ $task->number }}</a>
                            </td>
                            <td>{{ \App\Support\Formatting\TrFormatter::money((string) $task->grand_total) }} {{ $task->currency }}</td>
                            <td>{{ $task->due_date?->format('d.m.Y') ?? '—' }}</td>
                            <td><span class="badge">Taslak</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Bugün vadesi gelen taslak sipariş bulunamadı.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <aside class="panel v65-home-status">
            <strong>Hızlı Durum</strong>
            <div class="stack">
                @can('contact_aging.view')<a href="{{ route('finance.contact-aging') }}">Cari Yaşlandırma →</a>@endcan
                @can('stock.view')<a href="{{ route('stock.status') }}">Stok Durumu →</a>@endcan
                @can('finance_movements.view')<a href="{{ route('finance.operations') }}">Kasa / Banka Hareketleri →</a>@endcan
            </div>
        </aside>
    </div>

    <h2 class="v65-home-reports-title">Diğer Gerçek Dönem Göstergeleri</h2>
    @if($widgets === [])
        <section class="panel empty-state">
            Yetkinize uygun dashboard raporu bulunamadı.
        </section>
    @else
        <section class="dashboard-grid v65-home-kpis" aria-label="Güncel rapor göstergeleri">
            @foreach($widgets as $widget)
                @if($widget->url)
                    <a class="v65-home-kpi" href="{{ $widget->url }}">
                        <span>{{ $widget->title }}</span>
                        <strong>{{ $widget->value }}</strong>
                        <small>{{ $widget->description }}</small>
                    </a>
                @else
                    <div class="v65-home-kpi">
                        <span>{{ $widget->title }}</span>
                        <strong>{{ $widget->value }}</strong>
                        <small>{{ $widget->description }}</small>
                    </div>
                @endif
            @endforeach
        </section>
    @endif
</div>
