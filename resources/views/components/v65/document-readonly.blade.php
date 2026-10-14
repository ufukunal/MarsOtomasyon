@props(['document'])
<section {{ $attributes->class(['v65-doc-readonly']) }} aria-label="Kayıtlı belge detayları">
    <div class="v65-doc-readonly-head"><strong>Kayıtlı Belge Bilgileri</strong><small>Değişiklik yapılamaz</small></div>
    <dl class="v65-doc-metadata">
        <div><dt>Cari / Tedarikçi</dt><dd>{{ $document->contact?->title ?? '—' }}</dd></div>
        <div><dt>Belge Tarihi</dt><dd>{{ $document->document_date?->format('d.m.Y') ?? '—' }}</dd></div>
        @if($document->due_date)<div><dt>Vade Tarihi</dt><dd>{{ $document->due_date->format('d.m.Y') }}</dd></div>@endif
        @if($document->valid_until && $document->document_type === \App\Enums\DocumentType::Quote)
            <div><dt>Geçerlilik Tarihi</dt><dd>{{ $document->valid_until->format('d.m.Y') }}</dd></div>
        @endif
        <div><dt>Para Birimi</dt><dd>{{ $document->currency }}</dd></div>
    </dl>
    @if($document->notes)<p class="v65-doc-readonly-note"><strong>Not:</strong> {{ $document->notes }}</p>@endif
    <div class="v65-doc-table-scroll">
        <table class="data-table v65-doc-table">
            <thead><tr>
                <th scope="col">#</th><th scope="col">Tür</th><th scope="col">Ürün / Açıklama</th>
                <th scope="col">Birim</th><th scope="col" class="align-end">Miktar</th>
                <th scope="col" class="align-end">Birim Fiyat</th><th scope="col" class="align-end">İskonto</th>
                <th scope="col" class="align-end">KDV %</th><th scope="col" class="align-end">Satır Net</th>
            </tr></thead>
            <tbody>
                @forelse($document->lines as $line)
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td>{{ $line->line_kind === 'service' ? 'Hizmet' : 'Stok' }}</td>
                        <td>{{ $line->description ?: '—' }}</td>
                        <td>{{ $line->unit?->code ?? '—' }}</td>
                        <td class="align-end">{{ \App\Support\Formatting\TrFormatter::quantity((string) $line->quantity) }}</td>
                        <td class="align-end">{{ \App\Support\Formatting\TrFormatter::money((string) $line->unit_price) }}</td>
                        <td class="align-end">{{ \App\Support\Formatting\TrFormatter::money((string) $line->line_discount_amount) }}</td>
                        <td class="align-end">{{ $line->vat_rate }}</td>
                        <td class="align-end">{{ \App\Support\Formatting\TrFormatter::money((string) $line->line_total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-v65.empty-state title="Belgede satır bulunamadı." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <small class="v65-doc-line-note">Satır net tutarları KDV hariçtir; belge düzeyindeki iskonto ve vergiler toplamlar bölümünde gösterilir.</small>
</section>
