@extends('layouts.app')

@section('title', 'Güncelleme Merkezi')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">M33 / Release Management</p>
        <h1>Mars Güncelleme Merkezi</h1>
        <p>Yeni sürümleri imzalı release manifesti üzerinden doğrular. Bu ilk güven katmanı uygulama dosyalarını, veritabanını veya servisleri değiştirmez.</p>
    </div>
    <div class="page-actions">
        <a class="button-secondary" href="{{ route('operations.index') }}">Operasyon Merkezi</a>
    </div>
</section>

@if($checkError)
<section class="detail-card">
    <strong>{{ $checkError }}</strong>
</section>
@endif

<section class="detail-card">
    <h2>Kurulu Sistem</h2>
    <div class="form-grid">
        <div><strong>Kurulu sürüm</strong><br><code>{{ $updateStatus['current_version'] }}</code></div>
        <div><strong>Release kanalı</strong><br>{{ $updateStatus['channel'] }}</div>
        <div><strong>Manifest kaynağı</strong><br>{{ $updateStatus['manifest_configured'] ? 'Hazır' : 'Yapılandırılmadı' }}</div>
        <div><strong>İmza anahtarı</strong><br>{{ $updateStatus['public_key_configured'] ? 'Hazır' : 'Yapılandırılmadı' }}</div>
        <div><strong>Güvenilen host listesi</strong><br>{{ $updateStatus['allowed_hosts_configured'] ? 'Hazır' : 'Yapılandırılmadı' }}</div>
        <div><strong>Kurulum motoru</strong><br>{{ $updateStatus['install_enabled'] ? 'Aktif' : 'Bu aşamada kapalı' }}</div>
    </div>

    <form method="post" action="{{ route('operations.updates.check') }}">
        @csrf
        <button class="button-primary" type="submit" @disabled(! $updateStatus['manifest_configured'] || ! $updateStatus['public_key_configured'] || ! $updateStatus['allowed_hosts_configured'])>
            Güncellemeleri Güvenli Kontrol Et
        </button>
    </form>

    <p><strong>Güvenlik sınırı:</strong> Bu işlem yalnız allowlist içindeki HTTPS hostlarından manifest indirir; redirect takip etmez, allowlist şemasını ve RSA/SHA-256 imzasını doğrular, ardından SemVer uyumluluğunu hesaplar. Paket indirme veya komut çalıştırma yoktur.</p>
</section>

@if($checkResult)
<section class="detail-card">
    <h2>Doğrulanmış Release</h2>
    <div class="form-grid">
        <div><strong>Son sürüm</strong><br><code>{{ $checkResult['version'] }}</code></div>
        <div><strong>Güncelleme</strong><br>{{ $checkResult['update_available'] ? 'Yeni sürüm var' : 'Sistem güncel' }}</div>
        <div><strong>PHP uyumu</strong><br>{{ $checkResult['php_compatible'] ? 'Uygun' : 'Uygun değil' }}</div>
        <div><strong>Mars uyumu</strong><br>{{ $checkResult['app_compatible'] ? 'Uygun' : 'Uygun değil' }}</div>
        <div><strong>Toplam uyumluluk</strong><br>{{ $checkResult['compatible'] ? 'Uygun' : 'Kurulum engellenmeli' }}</div>
        <div><strong>Yayın zamanı</strong><br>{{ $checkResult['released_at'] }}</div>
    </div>

    <p><strong>Paket SHA-256:</strong> <code>{{ $checkResult['package_sha256'] }}</code></p>

    @if($checkResult['release_notes_url'])
        <p><a class="button-secondary" href="{{ $checkResult['release_notes_url'] }}" rel="noopener noreferrer" target="_blank">Sürüm Notları</a></p>
    @endif

    @if($checkResult['update_available'] && $checkResult['compatible'])
        <p>Release güven zincirini geçti. Kurulum/rollback motoru M33'ün sonraki diliminde etkinleştirilecektir.</p>
    @endif
</section>
@endif
@endsection
