<div class="stack">
    <section class="panel">
        <div class="form-row">
            @foreach (['stock' => 'Stok', 'documents' => 'Belgeler', 'contacts' => 'Cari', 'numbers' => 'Numaralar'] as $key => $label)
                <button type="button" wire:click="runNow('{{ $key }}')">{{ $label }} şimdi çalıştır</button>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <table class="data-table">
            <thead>
            <tr>
                <th>Kontrol</th>
                <th>Çalışma</th>
                <th>Kontrol edilen</th>
                <th>Fark</th>
                <th>Süre</th>
                <th>Detay</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($reports as $report)
                <tr>
                    <td>{{ $report->check_name }}</td>
                    <td>{{ $report->run_at?->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $report->checked_count }}</td>
                    <td>{{ $report->mismatch_count }}</td>
                    <td>{{ $report->duration_ms }} ms</td>
                    <td>
                        @if ($report->mismatch_count > 0)
                            <details>
                                <summary>Farkları göster</summary>
                                <pre>{{ json_encode($report->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        @else
                            Fark yok
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Henüz bütünlük kontrolü çalışmadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
