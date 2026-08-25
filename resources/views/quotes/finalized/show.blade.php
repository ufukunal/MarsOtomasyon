@extends('layouts.app')

@section('title', 'Finalized Teklif '.$revision->quote_number)

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Teklif / Finalized</p>
        <h1>{{ $revision->quote_number }} · R{{ $revision->revision_number }}</h1>
        <p>Readonly immutable ticari snapshot · {{ $quote->statusEnum()->label() }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('quotes.show', $quote->getKey()) }}" data-workspace-link>Teklife Dön</a>
        <a class="button-primary" href="{{ route('quotes.finalized.pdf', $quote->getKey()) }}">PDF İndir</a>
    </div>
</section>

<section class="detail-card">
    <div class="page-actions">
        <div>
            <p class="eyebrow">Ticari Otorite</p>
            <h2>Immutable R{{ $revision->revision_number }}</h2>
            <p>Bu yüzey yalnız selected revision verisini gösterir; mutable quote satırlarından veri okumaz.</p>
        </div>
        <span>{{ $rendererVersion }}</span>
    </div>
    <dl class="detail-grid">
        <div><dt>Cari</dt><dd>{{ $revision->account_code }} · {{ $revision->account_name }}</dd></div>
        <div><dt>Teklif Tarihi</dt><dd>{{ $revision->quote_date->format('d.m.Y') }}</dd></div>
        <div><dt>Geçerlilik</dt><dd>{{ $revision->valid_until?->format('d.m.Y') ?? '—' }}</dd></div>
        <div><dt>Karar</dt><dd>{{ $quote->statusEnum()->label() }} · {{ $quote->decision_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
        <div><dt>Para Birimi</dt><dd>{{ $revision->currency_code }}</dd></div>
        <div><dt>Revision Fingerprint</dt><dd><code>{{ $revision->content_fingerprint }}</code></dd></div>
    </dl>
</section>

@if ($document)
    <section class="detail-card">
        <h2>Dondurulmuş PDF</h2>
        <dl class="detail-grid">
            <div><dt>Renderer</dt><dd>{{ $document->renderer_version }}</dd></div>
            <div><dt>Üretim</dt><dd>{{ $document->generated_at->format('d.m.Y H:i:s') }}</dd></div>
            <div><dt>SHA-256</dt><dd><code>{{ $document->pdf_sha256 }}</code></dd></div>
            <div><dt>Boyut</dt><dd>{{ $document->fileAsset?->size_bytes ?? '—' }} byte</dd></div>
        </dl>
    </section>
@else
    <section class="detail-card">
        <h2>PDF henüz materialize edilmedi</h2>
        <p>İlk PDF indirmesinde bu renderer sürümünün byte/hash çıktısı private storage alanına bir kez yazılır ve sonrasında yeniden render edilmeden servis edilir.</p>
    </section>
@endif

<section class="detail-card statement-table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th><th>Ürün</th><th>Açıklama</th><th class="amount-cell">Miktar</th><th class="amount-cell">Birim Fiyat</th><th>KDV</th><th class="amount-cell">Net</th><th class="amount-cell">Vergi</th><th class="amount-cell">Toplam</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($revision->lines as $line)
                <tr>
                    <td>{{ $line->position }}</td>
                    <td>{{ $line->product_code }} · {{ $line->product_name }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="amount-cell">{{ $line->quantity }}</td>
                    <td class="amount-cell">{{ $line->unit_price }}</td>
                    <td>%{{ $line->tax_rate }}@if ($line->tax_zero_reason_code) · {{ $line->tax_zero_reason_code }}@endif</td>
                    <td class="amount-cell">{{ $line->net_total }}</td>
                    <td class="amount-cell">{{ $line->tax_total }}</td>
                    <td class="amount-cell">{{ $line->gross_total }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6">Toplam</th><th class="amount-cell">{{ $revision->net_total }}</th><th class="amount-cell">{{ $revision->tax_total }}</th><th class="amount-cell">{{ $revision->gross_total }} {{ $revision->currency_code }}</th>
            </tr>
        </tfoot>
    </table>
</section>

@if ($revision->note)
    <section class="detail-card"><h2>Not</h2><p>{{ $revision->note }}</p></section>
@endif
@endsection
