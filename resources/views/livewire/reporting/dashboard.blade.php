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
