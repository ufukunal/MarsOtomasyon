<div class="stack">
    @if($widgets === [])
        <section class="panel empty-state">
            Yetkinize uygun dashboard kartı bulunamadı.
        </section>
    @else
        <section class="dashboard-grid">
            @foreach($widgets as $widget)
                @if($widget->url)
                    <a class="dashboard-card" href="{{ $widget->url }}">
                        <span class="dashboard-card-title">{{ $widget->title }}</span>
                        <strong>{{ $widget->value }}</strong>
                        <small>{{ $widget->description }}</small>
                        <span class="dashboard-card-link">Rapora git →</span>
                    </a>
                @else
                    <div class="dashboard-card">
                        <span class="dashboard-card-title">{{ $widget->title }}</span>
                        <strong>{{ $widget->value }}</strong>
                        <small>{{ $widget->description }}</small>
                    </div>
                @endif
            @endforeach
        </section>
    @endif
</div>
