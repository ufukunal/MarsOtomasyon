<div class="stack">
    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Durum</span>
                <select wire:model.live="status">
                    <option value="">Tümü</option>
                    <option value="processing">İşleniyor</option>
                    <option value="done">Tamamlandı</option>
                    <option value="failed">Başarısız</option>
                </select>
            </label>
            <label>
                <span>Baskı tipi</span>
                <select wire:model.live="printType">
                    <option value="">Tümü</option>
                    <option value="a4">A4</option>
                    <option value="product_label">Ürün etiketi</option>
                    <option value="carton_label">Koli etiketi</option>
                    <option value="receipt">Fiş</option>
                    <option value="report">Rapor</option>
                </select>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Tip</th>
                    <th>Kaynak</th>
                    <th>Adet</th>
                    <th>Template</th>
                    <th>Revision</th>
                    <th>Seçim</th>
                    <th>Profil</th>
                    <th>Durum</th>
                    <th>Kullanıcı</th>
                    <th>Zaman</th>
                    <th>Sonuç</th>
                </tr>
                </thead>
                <tbody>
                @forelse($jobs as $job)
                    <tr>
                        <td>{{ $job->id }}</td>
                        <td>{{ $job->print_type?->value ?? $job->print_type }}</td>
                        <td>
                            @if($job->source_type && $job->source_id)
                                {{ $job->source_type }} #{{ $job->source_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $job->quantity }}</td>
                        <td>{{ $job->template?->template_key ?? ($job->result_metadata['template_key'] ?? '—') }}</td>
                        <td>{{ $job->template_revision_no ?? '—' }}</td>
                        <td>{{ $job->result_metadata['revision_selection'] ?? '—' }}</td>
                        <td>{{ $job->profile?->printer_name ?? 'Sistem varsayılanı' }}</td>
                        <td>{{ $job->status }}</td>
                        <td>{{ $job->user_id ?? 'system' }}</td>
                        <td>{{ $job->created_at?->format('d.m.Y H:i:s') }}</td>
                        <td>
                            @if($job->status === 'done')
                                {{ $job->result_metadata['filename'] ?? 'Tamamlandı' }}
                            @elseif($job->status === 'failed')
                                {{ $job->result_metadata['error_summary'] ?? 'Print operation failed.' }}
                            @else
                                İşleniyor
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="empty-state">Henüz yazdırma kaydı yok.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
