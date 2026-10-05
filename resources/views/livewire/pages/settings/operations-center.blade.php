<div class="stack">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="panel">
        <div class="toolbar">
            <div>
                <h2>Operasyon İşlemleri</h2>
                <p>Backup ve restore doğrulama işleri queue üzerinden yürütülür.</p>
            </div>
            <div class="toolbar">
                <button type="button" wire:click="runHealth">Health Kontrolü Çalıştır</button>
                @can('companies.update')
                    <button type="button" wire:click="backupNow">Şimdi Recovery-Set Backup Al</button>
                @endcan
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>Güncel Operational Health</h2>
        @if($latestHealth)
            <p>
                Durum: <strong>{{ strtoupper($latestHealth->overall_status) }}</strong>
                — {{ $latestHealth->checked_at?->format('d.m.Y H:i:s') }}
                — <code>{{ $latestHealth->correlation_id ?? '—' }}</code>
            </p>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                    <tr><th>Kontrol</th><th>Durum</th><th>Detay</th></tr>
                    </thead>
                    <tbody>
                    @foreach(($latestHealth->checks ?? []) as $name => $check)
                        <tr>
                            <td>{{ $name }}</td>
                            <td>{{ ($check['ok'] ?? false) ? 'OK' : 'FAIL' }} / {{ $check['status'] ?? 'unknown' }}</td>
                            <td>
                                {{ json_encode(
                                    collect($check)->except(['ok', 'status', 'severity'])->all(),
                                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                ) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">Henüz health sonucu yok.</div>
        @endif
    </section>

    <section class="panel">
        <h2>Deployment Geçmişi</h2>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                <tr>
                    <th>#</th><th>Release</th><th>Commit</th><th>Durum</th><th>Previous</th>
                    <th>Başlangıç</th><th>Bitiş</th><th>Hata</th>
                </tr>
                </thead>
                <tbody>
                @forelse($deployments as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td>{{ $run->release_id }}</td>
                        <td><code>{{ $run->commit_sha }}</code></td>
                        <td>{{ $run->status }}</td>
                        <td>{{ $run->previous_release_id ?? '—' }}</td>
                        <td>{{ $run->started_at?->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $run->finished_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                        <td>{{ $run->error_summary ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">Deployment kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p><small>Destructive DB rollback UI'dan çalıştırılmaz. Code rollback ve recovery escalation için canlı geçiş runbook'u kullanılır.</small></p>
    </section>

    <section class="panel">
        <h2>Recovery-Set Backup</h2>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                <tr>
                    <th>#</th><th>Recovery Set</th><th>Trigger</th><th>Durum</th><th>Başlangıç</th>
                    <th>Verified</th><th>Period</th><th>Manifest</th><th>İşlem</th>
                </tr>
                </thead>
                <tbody>
                @forelse($backups as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td><code>{{ $run->recovery_set_id }}</code></td>
                        <td>{{ $run->trigger_type }}</td>
                        <td>{{ $run->status }}</td>
                        <td>{{ $run->started_at?->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $run->verified_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                        <td>{{ count($run->period_manifest ?? []) }}</td>
                        <td>{{ $run->manifest_path ?? '—' }}</td>
                        <td>
                            @can('companies.update')
                                @if(in_array($run->status, ['done', 'verified'], true))
                                    <button type="button" wire:click="verifyBackup({{ $run->id }})">Restore Provası</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">Backup kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Restore Geçmişi</h2>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                <tr>
                    <th>#</th><th>Recovery Set</th><th>Target</th><th>Durum</th><th>Başlangıç</th><th>Bitiş</th><th>Hata</th>
                </tr>
                </thead>
                <tbody>
                @forelse($restores as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td><code>{{ $run->recovery_set_id }}</code></td>
                        <td>{{ $run->target_type }}</td>
                        <td>{{ $run->status }}</td>
                        <td>{{ $run->started_at?->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $run->finished_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                        <td>{{ $run->error_summary ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Restore kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Health Geçmişi</h2>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                <tr><th>#</th><th>Zaman</th><th>Durum</th><th>Correlation ID</th></tr>
                </thead>
                <tbody>
                @forelse($healthRuns as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td>{{ $run->checked_at?->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $run->overall_status }}</td>
                        <td><code>{{ $run->correlation_id ?? '—' }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-state">Health kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
