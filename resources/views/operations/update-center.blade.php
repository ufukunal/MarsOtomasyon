@extends('layouts.app')

@section('title', 'Güncelleme Merkezi')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">M33 / Release Management</p>
        <h1>Mars Güncelleme Merkezi</h1>
        <p>Yeni sürümleri imzalı release manifesti üzerinden doğrular. Kurulum motoru açılmadan önce tüm update talepleri kalıcı ve denetlenebilir bir lifecycle üzerinden ilerler.</p>
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
        <div><strong>Kurulum motoru</strong><br>{{ $updateStatus['install_enabled'] ? 'Aktif' : 'Bu aşamada kapalı' }}</div>
    </div>

    <form method="post" action="{{ route('operations.updates.check') }}">
        @csrf
        <button class="button-primary" type="submit" @disabled(! $updateStatus['manifest_configured'] || ! $updateStatus['public_key_configured'])>
            Güncellemeleri Güvenli Kontrol Et
        </button>
    </form>

    <p><strong>Güvenlik sınırı:</strong> Bu işlem yalnız HTTPS manifestini indirir, allowlist şemasını ve RSA/SHA-256 imzasını doğrular, ardından sürüm uyumluluğunu hesaplar. Paket indirme veya komut çalıştırma yoktur.</p>
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
        <p>Release güven zincirini geçti. Kurulum/rollback motoru ayrı execution diliminde etkinleştirilecektir.</p>
    @endif
</section>
@endif

<section class="detail-card">
    <h2>Güncelleme Geçmişi</h2>

    @if(count($updateRuns) === 0)
        <p>Henüz kalıcı update talebi oluşturulmadı.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hedef</th>
                        <th>Kanal</th>
                        <th>Durum</th>
                        <th>Hata</th>
                        <th>Oluşturma</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($updateRuns as $run)
                        <tr>
                            <td>{{ $run->id }}</td>
                            <td><code>{{ $run->target_version }}</code></td>
                            <td>{{ $run->channel }}</td>
                            <td>{{ $run->status }}</td>
                            <td>{{ $run->failure_code ?? '—' }}</td>
                            <td>{{ $run->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
