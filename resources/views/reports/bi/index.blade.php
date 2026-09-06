@extends('layouts.app')

@section('title', 'BI Dışa Aktarım')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Raporlar / Analitik</p>
        <h1>BI Dışa Aktarım</h1>
        <p>Curated, şirket kapsamlı ve şema versiyonlu datasetleri kontrollü olarak dışa aktarın veya planlayın.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('reports.index') }}">Raporlara Dön</a>
    </div>
</section>

@if(session('status'))
    <section class="detail-card"><p>{{ session('status') }}</p></section>
@endif

@foreach($datasets as $dataset)
<section class="detail-card">
    <div class="workspace-hero">
        <div>
            <p class="eyebrow">Dataset v{{ $dataset['schema_version'] }}</p>
            <h2>{{ $dataset['key'] }}</h2>
        </div>
    </div>

    <form method="POST" action="{{ route('reports.bi.export', ['datasetKey' => $dataset['key']]) }}" class="form-grid">
        @csrf
        <div>
            <label>Format</label>
            <select name="format"><option value="csv">CSV</option><option value="json">JSON</option></select>
        </div>
        <div>
            <label>Watermark</label>
            <input name="watermark" placeholder="Opsiyonel incremental watermark">
        </div>
        <div>
            <label>Alanlar</label>
            @foreach($dataset['fields'] as $field => $meta)
                <label style="display:block">
                    <input type="checkbox" name="fields[]" value="{{ $field }}" @checked(!$meta['pii'])>
                    {{ $field }} @if($meta['pii']) <span class="status-badge">PII</span> @endif
                </label>
            @endforeach
        </div>
        @if($canPii)
        <div>
            <label><input type="checkbox" name="include_pii" value="1"> PII alanlarını maskesiz dahil et</label>
        </div>
        @endif
        <div class="page-actions"><button class="button-primary" type="submit">Şimdi Dışa Aktar</button></div>
    </form>

    <hr>

    <form method="POST" action="{{ route('reports.bi.schedules.store') }}" class="form-grid">
        @csrf
        <input type="hidden" name="dataset_key" value="{{ $dataset['key'] }}">
        <div><label>Plan Anahtarı</label><input name="schedule_key" required maxlength="128" placeholder="daily-{{ $dataset['key'] }}"></div>
        <div><label>Format</label><select name="format"><option value="csv">CSV</option><option value="json">JSON</option></select></div>
        <div><label>Periyot (dakika)</label><input type="number" name="interval_minutes" min="5" value="1440" required></div>
        <div><label>İlk Çalışma</label><input type="datetime-local" name="next_run_at" required></div>
        <div>
            <label>Alanlar</label>
            @foreach($dataset['fields'] as $field => $meta)
                <label style="display:block"><input type="checkbox" name="fields[]" value="{{ $field }}" @checked(!$meta['pii'])> {{ $field }}</label>
            @endforeach
        </div>
        @if($canPii)
        <div><label><input type="checkbox" name="include_pii" value="1"> PII dahil</label></div>
        @endif
        <div class="page-actions"><button class="button-primary" type="submit">Planı Kaydet</button></div>
    </form>
</section>
@endforeach

<section class="detail-card">
    <h2>Planlı Dışa Aktarımlar</h2>
    <div class="statement-table-card">
        <table class="data-table">
            <thead><tr><th>Anahtar</th><th>Dataset</th><th>Format</th><th>Periyot</th><th>Sonraki</th><th>Son Çalışma</th><th>Durum</th></tr></thead>
            <tbody>
            @forelse($schedules as $schedule)
                <tr>
                    <td>{{ $schedule->schedule_key }}</td>
                    <td>{{ $schedule->dataset_key }} v{{ $schedule->schema_version }}</td>
                    <td>{{ strtoupper($schedule->format) }}</td>
                    <td>{{ $schedule->interval_minutes }} dk</td>
                    <td>{{ $schedule->next_run_at }}</td>
                    <td>{{ $schedule->last_run_at ?? '—' }}</td>
                    <td>{{ $schedule->last_error ? 'Hata: '.$schedule->last_error : ($schedule->is_enabled ? 'Aktif' : 'Pasif') }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Planlı BI export bulunmuyor.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="detail-card">
    <h2>Son Export Runları</h2>
    <div class="statement-table-card">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Dataset</th><th>Durum</th><th>Satır</th><th>Watermark</th><th>SHA-256</th><th>Hata</th></tr></thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td>{{ $run->id }}</td>
                    <td>{{ $run->dataset_key }} v{{ $run->schema_version }}</td>
                    <td>{{ $run->status }}</td>
                    <td>{{ $run->row_count }}</td>
                    <td>{{ $run->output_watermark ?? '—' }}</td>
                    <td><code>{{ $run->artifact_sha256 ?? '—' }}</code></td>
                    <td>{{ $run->last_error ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">BI export runı bulunmuyor.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
