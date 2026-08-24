@extends('layouts.settings')

@section('title', 'Dosya Detayı')
@section('heading', 'Dosya Detayı')

@section('content')
    @php($asset = $attachment->fileAsset)

    <div class="page-actions">
        <p>Dosya kaydı salt okunur görünümü.</p>
        <div>
            <a href="{{ route('settings.files.index') }}">Listeye Dön</a>
            @if (! $attachment->isDetached())
                · <a href="{{ route('settings.files.download', $attachment->getKey()) }}">İndir</a>
            @endif
        </div>
    </div>

    <section class="detail-card">
        <dl class="detail-grid">
            <div><dt>Dosya Adı</dt><dd>{{ $asset?->original_name ?? '—' }}</dd></div>
            <div><dt>Etiket</dt><dd>{{ $attachment->label ?: '—' }}</dd></div>
            <div><dt>MIME Türü</dt><dd>{{ $asset?->mime_type ?? '—' }}</dd></div>
            <div><dt>Boyut</dt><dd>{{ $asset ? number_format((int) $asset->size_bytes / 1024, 1, ',', '.') . ' KB' : '—' }}</dd></div>
            <div><dt>SHA-256</dt><dd><code>{{ $asset?->sha256 ?? '—' }}</code></dd></div>
            <div><dt>Storage</dt><dd>Private</dd></div>
            <div><dt>Durum</dt><dd>{{ $attachment->isDetached() ? 'Bağlantı Kaldırıldı' : 'Aktif' }}</dd></div>
            <div><dt>Yüklenme</dt><dd>{{ $attachment->attached_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
        </dl>
    </section>

    @if (! $attachment->isDetached())
        @can('core.file.manage')
            <form method="post" action="{{ route('settings.files.detach', $attachment->getKey()) }}">
                @csrf
                <button type="submit">Dosya Bağlantısını Kaldır</button>
            </form>
        @endcan
    @else
        <div class="notice-info">Bu bağlantı kaldırıldı. Orijinal dosya arşiv kaydı olarak korunur ve normal indirme akışına kapalıdır.</div>
    @endif
@endsection
