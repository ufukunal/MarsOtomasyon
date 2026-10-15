<div class="stack v65-list-page">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="panel">
        <div class="toolbar v65-operations-header">
            <div>
                <h2>Operasyon İşlemleri</h2>
                <p>Backup ve restore doğrulama işleri queue üzerinden yürütülür.</p>
            </div>
            <div class="toolbar v65-operations-actions">
                <button class="button-secondary" type="button" wire:click="runHealth">Health Kontrolü Çalıştır</button>
                @can('companies.update')
                    <button type="button" wire:click="backupNow">Şimdi Recovery-Set Backup Al</button>
                @endcan
            </div>
        </div>
    </section>

    <section class="panel">
        <x-v65.list-heading title="Güncel Operational Health" />
        @if($latestHealth)
            <p>
                Durum: <strong>{{ \App\Support\Formatting\BusinessLabelFormatter::status($latestHealth->overall_status) }}</strong>
                — {{ $latestHealth->checked_at?->format('d.m.Y H:i:s') }}
                — <code>{{ $latestHealth->correlation_id ?? '—' }}</code>
            </p>
            <div class="table-scroll v65-list-table">
                <table class="data-table">
                    <thead>
                    <tr><th>Kontrol</th><th>Durum</th><th>Detay</th></tr>
                    </thead>
                    <tbody>
                    @foreach(($latestHealth->checks ?? []) as $name => $check)
                        <tr>
                            <td>{{ $name }}</td>
                            <td>{{ ($check['ok'] ?? false) ? 'Başarılı' : 'Başarısız' }} / {{ \App\Support\Formatting\BusinessLabelFormatter::status($check['status'] ?? 'unknown') }}</td>
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
        <x-v65.list-heading title="Deployment Geçmişi" />
        <div class="table-scroll v65-list-table">
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
                        <td>{{ \App\Support\Formatting\BusinessLabelFormatter::status($run->status) }}</td>
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
        <x-v65.list-heading title="Recovery-Set Backup" />
        <div class="table-scroll v65-list-table">
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
                        <td>{{ \App\Support\Formatting\BusinessLabelFormatter::status($run->status) }}</td>
                        <td>{{ $run->started_at?->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $run->verified_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                        <td>{{ count($run->period_manifest ?? []) }}</td>
                        <td>{{ $run->manifest_path ?? '—' }}</td>
                        <td>
                            @can('companies.update')
                                @if(in_array($run->status, ['done', 'verified'], true))
                                    <button class="button-secondary" type="button" wire:click="verifyBackup({{ $run->id }})">Restore Provası</button>
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
        <x-v65.list-heading title="Restore Geçmişi" />
        <div class="table-scroll v65-list-table">
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
                        <td>{{ \App\Support\Formatting\BusinessLabelFormatter::status($run->status) }}</td>
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
        <x-v65.list-heading title="Health Geçmişi" />
        <div class="table-scroll v65-list-table">
            <table class="data-table">
                <thead>
                <tr><th>#</th><th>Zaman</th><th>Durum</th><th>Correlation ID</th></tr>
                </thead>
                <tbody>
                @forelse($healthRuns as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td>{{ $run->checked_at?->format('d.m.Y H:i:s') }}</td>
                        <td>{{ \App\Support\Formatting\BusinessLabelFormatter::status($run->overall_status) }}</td>
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
