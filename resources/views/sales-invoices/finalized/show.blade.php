@extends('layouts.app')

@section('title', 'Finalized Fatura '.$invoice->number)

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Faturalar / Finalized</p>
        <h1>{{ $invoice->number }}</h1>
        <p>Readonly finalized ticari snapshot · {{ $invoice->statusEnum()->label() }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('sales-invoices.show', $invoice->getKey()) }}">Faturaya Dön</a>
        <a class="button-primary" href="{{ route('sales-invoices.finalized.pdf', $invoice->getKey()) }}">PDF İndir</a>
    </div>
</section>

<section class="detail-card">
    <div class="page-actions">
        <div><p class="eyebrow">Belge Otoritesi</p><h2>Immutable finalized snapshot</h2><p>PDF renderer sürümü değişirse yeni document version oluşur; mevcut byte/hash hiçbir zaman mutate edilmez.</p></div>
        <span>{{ $rendererVersion }}</span>
    </div>
    <dl class="detail-grid">
        <div><dt>Müşteri</dt><dd>{{ $invoice->customer_legal_name }}</dd></div>
        <div><dt>Fatura Tarihi</dt><dd>{{ $invoice->invoice_date?->format('d.m.Y') }}</dd></div>
        <div><dt>Kesinleşme</dt><dd>{{ $invoice->finalized_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
        <div><dt>İptal</dt><dd>{{ $invoice->cancelled_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
        <div><dt>Para Birimi</dt><dd>{{ $invoice->currency_code }}</dd></div>
        <div><dt>Genel Toplam</dt><dd>{{ $invoice->gross_total }} {{ $invoice->currency_code }}</dd></div>
    </dl>
</section>

@if($document)
<section class="detail-card"><h2>Dondurulmuş PDF</h2><dl class="detail-grid">
    <div><dt>Renderer</dt><dd>{{ $document->renderer_version }}</dd></div>
    <div><dt>Üretim</dt><dd>{{ $document->generated_at->format('d.m.Y H:i:s') }}</dd></div>
    <div><dt>SHA-256</dt><dd><code>{{ $document->pdf_sha256 }}</code></dd></div>
    <div><dt>Boyut</dt><dd>{{ $document->fileAsset?->size_bytes ?? '—' }} byte</dd></div>
</dl></section>
@else
<section class="detail-card"><h2>PDF henüz materialize edilmedi</h2><p>İlk indirmede renderer çıktısı private storage alanına bir kez yazılır ve sonraki indirmelerde SHA-256 doğrulanarak aynı bytes servis edilir.</p></section>
@endif

<section class="detail-card statement-table-card"><table class="data-table">
<thead><tr><th>#</th><th>Ürün</th><th class="amount-cell">Miktar</th><th class="amount-cell">Birim Fiyat</th><th>KDV</th><th class="amount-cell">Net</th><th class="amount-cell">Toplam</th></tr></thead>
<tbody>@foreach($invoice->lines as $line)<tr>
<td>{{ $line->position }}</td><td>{{ $line->product_code }} · {{ $line->product_name }}</td><td class="amount-cell">{{ $line->quantity }}</td><td class="amount-cell">{{ $line->unit_price }}</td><td>%{{ $line->tax_rate }}</td><td class="amount-cell">{{ $line->net_total }}</td><td class="amount-cell">{{ $line->gross_total }}</td>
</tr>@endforeach</tbody>
<tfoot><tr><th colspan="5">Toplam</th><th class="amount-cell">{{ $invoice->net_total }}</th><th class="amount-cell">{{ $invoice->gross_total }} {{ $invoice->currency_code }}</th></tr></tfoot>
</table></section>

<section class="detail-card"><h2>E-Belge Provider-Neutral Lifecycle</h2>
@if($eDocumentEvents->isEmpty())
<p>Henüz e-belge lifecycle event'i yok. Provider entegrasyonları bu append-only seam üzerinden ilerleyecek.</p>
@else
<table class="data-table"><thead><tr><th>Tip</th><th>Event</th><th>Provider</th><th>Harici ID</th><th>Zaman</th></tr></thead><tbody>
@foreach($eDocumentEvents as $event)<tr><td>{{ $event->documentTypeEnum()->label() }}</td><td>{{ $event->eventTypeEnum()->label() }}</td><td>{{ $event->provider_key ?? '—' }}</td><td>{{ $event->external_document_id ?? '—' }}</td><td>{{ $event->occurred_at->format('d.m.Y H:i:s') }}</td></tr>@endforeach
</tbody></table>
@endif
</section>
@endsection
