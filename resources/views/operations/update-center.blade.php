@extends('layouts.app')

@section('title', 'Güncelleme Merkezi')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">M33 / Release Management</p>
        <h1>Mars Güncelleme Merkezi</h1>
        <p>İmzalı release doğrulama, güvenli staging, zorunlu yedek, host deploy-agent aktivasyonu, health gate ve rollback zinciri.</p>
    </div>
    <div class="page-actions">
        <a class="button-secondary" href="{{ route('operations.index') }}" data-workspace-link>Operasyon Merkezi</a>
    </div>
</section>

@if($checkError)
    <div class="notice-error" role="alert"><strong>{{ $checkError }}</strong></div>
@endif

<section class="detail-card">
    <h2>Kurulu Sistem</h2>
    <div class="form-grid">
        <div><strong>Kurulu sürüm</strong><br><code>{{ $updateStatus['current_version'] }}</code></div>
        <div><strong>Release kanalı</strong><br>{{ $updateStatus['channel'] }}</div>
        <div><strong>Manifest kaynağı</strong><br>{{ $updateStatus['manifest_configured'] ? 'Hazır' : 'Yapılandırılmadı' }}</div>
        <div><strong>İmza anahtarı</strong><br>{{ $updateStatus['public_key_configured'] ? 'Hazır' : 'Yapılandırılmadı' }}</div>
        <div><strong>Güvenilen host listesi</strong><br>{{ $updateStatus['allowed_hosts_configured'] ? 'Hazır' : 'Yapılandırılmadı' }}</div>
        <div><strong>Staging</strong><br>{{ $updateStatus['staging_configured'] ? 'Hazır' : 'Yapılandırılmadı' }}</div>
        <div><strong>Deploy agent</strong><br>{{ $updateStatus['agent_enabled'] ? 'Aktif' : 'Kapalı' }}</div>
    </div>

    <form method="post" action="{{ route('operations.updates.check') }}">
        @csrf
        <button class="button-primary" type="submit" @disabled(! $updateStatus['manifest_configured'] || ! $updateStatus['public_key_configured'] || ! $updateStatus['allowed_hosts_configured'])>
            Güncellemeleri Güvenli Kontrol Et
        </button>
    </form>

    <p><strong>Güvenlik:</strong> Manifest ve paket yalnız allowlist içindeki HTTPS hostlarından alınır; redirect kapalıdır. Manifest RSA/SHA-256, paket SHA-256 ile doğrulanır. Web prosesi canlı uygulama dosyalarını değiştirmez ve manifestten komut çalıştırmaz.</p>
</section>

@if($checkResult)
<section class="detail-card">
    <h2>Doğrulanmış Release</h2>
    <div class="form-grid">
        <div><strong>Son sürüm</strong><br><code>{{ $checkResult['version'] }}</code></div>
        <div><strong>Güncelleme</strong><br>{{ $checkResult['update_available'] ? 'Yeni sürüm var' : 'Sistem güncel' }}</div>
        <div><strong>PHP uyumu</strong><br>{{ $checkResult['php_compatible'] ? 'Uygun' : 'Uygun değil' }}</div>
        <div><strong>Mars uyumu</strong><br>{{ $checkResult['app_compatible'] ? 'Uygun' : 'Uygun değil' }}</div>
        <div><strong>Toplam uyumluluk</strong><br>{{ $checkResult['compatible'] ? 'Uygun' : 'Kurulum engellendi' }}</div>
        <div><strong>Yayın zamanı</strong><br>{{ $checkResult['released_at'] }}</div>
    </div>

    <p><strong>Paket SHA-256:</strong> <code>{{ $checkResult['package_sha256'] }}</code></p>

    <div class="page-actions">
        @if($checkResult['release_notes_url'])
            <a class="button-secondary" href="{{ $checkResult['release_notes_url'] }}" rel="noopener noreferrer" target="_blank">Sürüm Notları</a>
        @endif
        @if($checkResult['update_available'] && $checkResult['compatible'])
            <form method="post" action="{{ route('operations.updates.stage') }}">
                @csrf
                <button class="button-primary" type="submit">Paketi Doğrula ve Stage Et</button>
            </form>
        @endif
    </div>
</section>
@endif

<section class="statement-table-card">
    <h2>Güncelleme Geçmişi</h2>
    <table class="data-table">
        <thead>
        <tr>
            <th>Run</th>
            <th>Sürüm</th>
            <th>Kanal</th>
            <th>Durum</th>
            <th>Başlangıç</th>
            <th>Bitiş</th>
            <th>Hata</th>
            <th>İşlem</th>
        </tr>
        </thead>
        <tbody>
        @forelse($updateRuns as $run)
            <tr>
                <td>#{{ $run->id }}</td>
                <td><code>{{ $run->target_version }}</code></td>
                <td>{{ $run->channel }}</td>
                <td><strong>{{ $run->status }}</strong></td>
                <td>{{ $run->created_at }}</td>
                <td>{{ $run->finished_at ?? '—' }}</td>
                <td>{{ $run->failure_code ? $run->failure_code.' · '.$run->failure_message : '—' }}</td>
                <td>
                    @if($run->status === 'staged')
                        <form method="post" action="{{ route('operations.updates.apply', $run->id) }}">
                            @csrf
                            <button class="button-primary" type="submit" @disabled(! $updateStatus['agent_enabled'])>Kurulumu Kuyruğa Al</button>
                        </form>
                    @elseif($run->status === 'completed')
                        <form method="post" action="{{ route('operations.updates.rollback', $run->id) }}">
                            @csrf
                            <button class="button-secondary" type="submit" @disabled(! $updateStatus['agent_enabled'])>Rollback</button>
                        </form>
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8">Henüz güncelleme run kaydı yok.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
