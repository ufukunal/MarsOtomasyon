@extends('layouts.app')

@section('title', 'Güncelleme Merkezi')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">M33 / Release Management</p>
        <h1>Güncelleme Merkezi</h1>
        <p>İmzalı release doğrulaması, readiness, staging, host executor, health check ve rollback lifecycle yönetimi.</p>
    </div>
    <div class="page-actions">
        <a class="button-secondary" href="{{ route('operations.index') }}">Operasyon Merkezi</a>
        <form method="post" action="{{ route('operations.updates.check') }}">
            @csrf
            <button class="button-primary" type="submit" @disabled(! $updateStatus['manifest_configured'] || ! $updateStatus['public_key_configured'] || ! $updateStatus['allowed_hosts_configured'])>
                Güncellemeleri Kontrol Et
            </button>
        </form>
    </div>
</section>

@if($checkError)
    <div class="notice-error" role="alert">{{ $checkError }}</div>
@endif

<section class="detail-card">
    <h2>Sistem ve Güven Durumu</h2>
    <div class="form-grid">
        <div><strong>Current Version</strong><br><code>{{ $updateStatus['current_version'] }}</code></div>
        <div><strong>Current Build SHA</strong><br><code>{{ $updateStatus['current_build'] }}</code></div>
        <div><strong>Channel</strong><br>{{ $updateStatus['channel'] }}</div>
        <div><strong>Manifest Source</strong><br>{{ $updateStatus['manifest_configured'] ? $updateStatus['manifest_url'] : 'Yapılandırılmadı' }}</div>
        <div><strong>Trust Status</strong><br>{{ $updateStatus['public_key_configured'] && $updateStatus['allowed_hosts_configured'] ? 'Hazır' : 'Eksik yapılandırma' }}</div>
        <div><strong>Database</strong><br>{{ $updateStatus['database_ready'] ? 'Hazır' : 'Hazır değil' }}</div>
        <div><strong>Valkey</strong><br>{{ $updateStatus['valkey_ready'] ? 'Hazır' : 'Hazır değil' }}</div>
        <div><strong>Worker / Scheduler</strong><br>{{ $updateStatus['worker_ready'] && $updateStatus['scheduler_ready'] ? 'Hazır' : 'Hazır değil' }}</div>
        <div><strong>Disk</strong><br>{{ $updateStatus['disk_ready'] ? 'Hazır' : 'Yetersiz' }} @if($updateStatus['disk_free_bytes'] !== null) · {{ number_format($updateStatus['disk_free_bytes'] / 1073741824, 1) }} GiB @endif</div>
        <div><strong>Backup Readiness</strong><br>{{ $updateStatus['backup_ready'] ? 'Hazır' : 'Engelli' }} · {{ $updateStatus['backup_reason'] }}</div>
        <div><strong>Offsite Backup</strong><br>{{ $updateStatus['backup_offsite_required'] ? 'Zorunlu' : 'Zorunlu değil' }}</div>
        <div><strong>Apply Readiness</strong><br>{{ $updateStatus['apply_ready'] ? 'Hazır' : 'Fail closed' }}</div>
    </div>
</section>

@if($checkResult)
<section class="detail-card">
    <h2>Doğrulanmış Release</h2>
    <div class="form-grid">
        <div><strong>Latest Version</strong><br><code>{{ $checkResult['version'] }}</code></div>
        <div><strong>Update Available</strong><br>{{ $checkResult['update_available'] ? 'Evet' : 'Hayır' }}</div>
        <div><strong>PHP Compatibility</strong><br>{{ $checkResult['php_compatible'] ? 'Uygun' : 'Uygun değil' }}</div>
        <div><strong>Mars Compatibility</strong><br>{{ $checkResult['app_compatible'] ? 'Uygun' : 'Uygun değil' }}</div>
        <div><strong>Release Time</strong><br>{{ $checkResult['released_at'] }}</div>
        <div><strong>Manifest Digest</strong><br><code>{{ $checkResult['manifest_sha256'] }}</code></div>
        <div><strong>Package Digest</strong><br><code>{{ $checkResult['package_sha256'] }}</code></div>
    </div>

    <div class="page-actions">
        @if($checkResult['release_notes_url'])
            <a class="button-secondary" href="{{ $checkResult['release_notes_url'] }}" rel="noopener noreferrer" target="_blank">Sürüm Notları</a>
        @endif
        <form method="post" action="{{ route('operations.updates.prepare') }}">
            @csrf
            <input type="hidden" name="request_key" value="{{ $requestKey }}">
            <button class="button-primary" type="submit" @disabled(! $checkResult['update_available'] || ! $checkResult['compatible'] || $activeRun !== null)>
                Güncellemeyi Hazırla
            </button>
        </form>
    </div>
</section>
@endif

@if($activeRun)
@php
    $activeMetadata = json_decode((string) ($activeRun->metadata ?? '{}'), true);
    $activeMetadata = is_array($activeMetadata) ? $activeMetadata : [];
@endphp
<section class="detail-card">
    <h2>Aktif Güncelleme Run</h2>
    <div class="form-grid">
        <div><strong>Run</strong><br>#{{ $activeRun->id }}</div>
        <div><strong>Target</strong><br><code>{{ $activeRun->target_version }}</code></div>
        <div><strong>Channel</strong><br>{{ $activeRun->channel }}</div>
        <div><strong>Lifecycle</strong><br><code>{{ $activeRun->status }}</code></div>
        <div><strong>Failure</strong><br>{{ $activeRun->failure_code ?? '—' }}</div>
        <div><strong>Apply Request</strong><br>{{ $activeMetadata['apply_requested_at'] ?? '—' }}</div>
    </div>

    <div class="page-actions">
        <form method="post" action="{{ route('operations.updates.apply', ['run' => $activeRun->id]) }}">
            @csrf
            <button class="button-primary" type="submit" @disabled($activeRun->status !== 'staged' || ! $updateStatus['apply_ready'] || isset($activeMetadata['apply_requested_at']))>
                Güncellemeyi Uygula
            </button>
        </form>
        <form method="post" action="{{ route('operations.updates.rollback', ['run' => $activeRun->id]) }}">
            @csrf
            <button class="button-secondary" type="submit" @disabled(! in_array($activeRun->status, ['applying', 'health_check'], true))>
                Rollback
            </button>
        </form>
    </div>
</section>
@endif

<section class="detail-card">
    <h2>Güncelleme Geçmişi</h2>
    @if(count($updateRuns) === 0)
        <p>Henüz update run kaydı yok.</p>
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
                    <th>Başlangıç</th>
                    <th>Bitiş</th>
                </tr>
                </thead>
                <tbody>
                @foreach($updateRuns as $run)
                    <tr>
                        <td>#{{ $run->id }}</td>
                        <td><code>{{ $run->target_version }}</code></td>
                        <td>{{ $run->channel }}</td>
                        <td><code>{{ $run->status }}</code></td>
                        <td>{{ $run->failure_code ?? '—' }}</td>
                        <td>{{ $run->created_at }}</td>
                        <td>{{ $run->finished_at ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
