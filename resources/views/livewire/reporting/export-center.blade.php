<div class="stack v65-list-page">
    <section class="panel stack">
        <h2>Dışa Aktarım Geçmişi</h2>
        <div class="table-scroll v65-list-table">
            <table class="data-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Rapor</th>
                    <th>Format</th>
                    <th>Dönem</th>
                    <th>Durum</th>
                    <th>İlerleme</th>
                    <th>Oluşturma</th>
                    <th>Sonuç</th>
                </tr>
                </thead>
                <tbody>
                @forelse($jobs as $job)
                    <tr>
                        <td>{{ $job->id }}</td>
                        <td>{{ $job->report_key }}</td>
                        <td>{{ strtoupper($job->format) }}</td>
                        <td>{{ implode(', ', $job->periods ?? []) }}</td>
                        <td>{{ $job->status }}</td>
                        <td>{{ $job->progress === null ? '—' : $job->progress.'%' }}</td>
                        <td>{{ $job->created_at?->format('d.m.Y H:i:s') }}</td>
                        <td>
                            @if($job->status === 'done' && $job->storage_path)
                                <a class="button-secondary" href="{{ route('reports.exports.download', $job) }}">İndir</a>
                            @elseif($job->status === 'failed')
                                {{ $job->error_summary ?: 'Üretim başarısız.' }}
                            @elseif($job->status === 'done')
                                Dosya süresi doldu.
                            @else
                                Bekleniyor
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-state">Henüz queue export kaydı yok.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
