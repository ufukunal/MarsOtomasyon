@extends('layouts.settings')

@section('title', 'CAD / 3D Önizleme')
@section('heading', 'CAD / 3D Önizleme')

@section('content')
    <div class="page-actions">
        <div>
            <p><strong>{{ $asset->original_name }}</strong></p>
            <p>Read-only teknik önizleme · Orijinal private dosya değişmez.</p>
        </div>
        <div>
            <a href="{{ route('settings.files.show', $attachment->getKey()) }}">Dosya Detayına Dön</a>
            · <a href="{{ route('settings.files.download', $attachment->getKey()) }}">Orijinali İndir</a>
            @can('core.file.manage')
                · <a href="{{ route('settings.files.cad.policy') }}">Cloud Politikası</a>
            @endcan
        </div>
    </div>

    <section class="detail-card">
        <dl class="detail-grid">
            <div><dt>Format</dt><dd>{{ strtoupper($extension) }}</dd></div>
            <div><dt>Provider</dt><dd>{{ $provider === 'aps' ? 'Autodesk APS' : 'Mars Local Viewer' }}</dd></div>
            <div><dt>Kaynak SHA-256</dt><dd><code>{{ $asset->sha256 }}</code></dd></div>
            <div><dt>Yetki</dt><dd>Salt okunur</dd></div>
        </dl>
    </section>

    @if ($extension === 'max')
        <div class="notice-info">
            <strong>.MAX native parse edilmez.</strong>
            Önizleme yalnız doğrulanmış provider veya kontrollü Autodesk/3ds Max conversion yolu ile sağlanabilir.
        </div>
    @elseif ($provider === 'local' && ! in_array($extension, ['dxf', 'obj'], true))
        <div class="notice-info">
            Mars Local Viewer yalnız DXF ve OBJ pilot formatlarını işler. DWG için Autodesk APS seçin.
        </div>
    @elseif ($provider === 'aps' && ! in_array($extension, ['dwg', 'dxf', 'obj'], true))
        <div class="notice-info">Seçili APS pilot capability bu formatı desteklemiyor.</div>
    @else
        @if ($job === null)
            <form method="post" action="{{ route('settings.files.cad.request', $attachment->getKey()) }}">
                @csrf
                <input type="hidden" name="provider" value="{{ $provider }}">
                <button type="submit">Önizleme Oluştur</button>
            </form>
        @elseif ($job->status === 'failed')
            <div class="notice-error" role="alert">
                <strong>Önizleme oluşturulamadı.</strong>
                <p>{{ $job->failure_message ?: 'Provider kontrollü hata döndürdü.' }}</p>
                <code>{{ $job->failure_code }}</code>
            </div>
            @can('core.file.manage')
                <form method="post" action="{{ route('settings.files.cad.rebuild', [$attachment->getKey(), $job->getKey()]) }}">
                    @csrf
                    <button type="submit">Derivative Yeniden Oluştur</button>
                </form>
            @endcan
        @elseif ($job->status === 'processing')
            <div
                class="notice-info"
                data-cad-processing
                data-status-url="{{ route('settings.files.cad.refresh', [$attachment->getKey(), $job->getKey()]) }}"
                data-csrf="{{ csrf_token() }}"
            >
                Translation provider tarafında işleniyor. Bu sayfa sonucu otomatik yeniler.
            </div>
            <script src="{{ asset('js/cad-processing.js') }}" defer></script>
        @elseif ($job->status === 'ready' && $provider === 'local')
            <div
                class="detail-card"
                data-cad-local-viewer
                data-source-url="{{ route('settings.files.cad.source', $attachment->getKey()) }}"
                data-extension="{{ $extension }}"
                data-source-sha="{{ $asset->sha256 }}"
            >
                <div class="page-actions">
                    <strong>{{ $extension === 'obj' ? '3D Model' : '2D CAD' }}</strong>
                    <span data-cad-render-status>Yükleniyor…</span>
                </div>
                <div data-cad-toolbar>
                    <button type="button" data-cad-fit>Fit</button>
                    <button type="button" data-cad-zoom-in>+</button>
                    <button type="button" data-cad-zoom-out>−</button>
                </div>
                <div data-cad-surface-host style="min-height: 480px; overflow: hidden; border: 1px solid #d0d5dd;"></div>
            </div>
            <script src="{{ asset('js/cad-local-viewer.js') }}" defer></script>
        @elseif ($job->status === 'ready' && $provider === 'aps')
            <div class="notice-info" data-cad-aps-ready>
                <strong>APS derivative hazır.</strong>
                <p>
                    Güvenlik politikası gereği tarayıcı üçüncü taraf CDN kodu yüklemez. Provider translation sonucu kaydedildi;
                    orijinal private dosya ve işlem kaydı değişmeden korunur.
                </p>
            </div>
        @endif
    @endif

    <section class="detail-card">
        <h2>Provider Seçimi</h2>
        <p>
            <a href="{{ route('settings.files.cad.show', ['attachment' => $attachment->getKey(), 'provider' => 'local']) }}">Mars Local (DXF/OBJ)</a>
            ·
            <a href="{{ route('settings.files.cad.show', ['attachment' => $attachment->getKey(), 'provider' => 'aps']) }}">Autodesk APS (DWG/DXF/OBJ)</a>
        </p>
    </section>
@endsection
