@extends('layouts.app')

@section('title', 'Teklif '.$revision->quote_number.' R'.$revision->revision_number)

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Teklif / Immutable Revision</p>
        <h1>{{ $revision->quote_number }} · R{{ $revision->revision_number }}</h1>
        <p>{{ $revision->account_name }} · {{ $revision->created_at->format('d.m.Y H:i:s') }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('quotes.show', $revision->quote_id) }}" data-workspace-link>Güncel Teklif</a>
        <a href="{{ route('quotes.index') }}" data-workspace-link>Teklifler</a>
    </div>
</section>

<section class="detail-card">
    <dl class="detail-grid">
        <div><dt>Cari</dt><dd>{{ $revision->account_code }} · {{ $revision->account_name }}</dd></div>
        <div><dt>Teklif Tarihi</dt><dd>{{ $revision->quote_date->format('d.m.Y') }}</dd></div>
        <div><dt>Geçerlilik</dt><dd>{{ $revision->valid_until?->format('d.m.Y') ?? '—' }}</dd></div>
        <div><dt>Para Birimi</dt><dd>{{ $revision->currency_code }}</dd></div>
        <div><dt>Belge İskonto</dt><dd>%{{ $revision->document_discount_rate }}</dd></div>
        <div><dt>Fingerprint</dt><dd><code>{{ $revision->content_fingerprint }}</code></dd></div>
    </dl>
</section>

<section class="detail-card statement-table-card">
<table class="data-table">
<thead><tr><th>#</th><th>Ürün</th><th>Açıklama</th><th class="amount-cell">Miktar</th><th class="amount-cell">Birim Fiyat</th><th>KDV</th><th class="amount-cell">Net</th><th class="amount-cell">Vergi</th><th class="amount-cell">Toplam</th></tr></thead>
<tbody>
@foreach($revision->lines as $line)
<tr>
    <td>{{ $line->position }}</td>
    <td>{{ $line->product_code }} · {{ $line->product_name }}</td>
    <td>{{ $line->description }}</td>
    <td class="amount-cell">{{ $line->quantity }}</td>
    <td class="amount-cell">{{ $line->unit_price }}</td>
    <td>{{ $line->tax_code }} · %{{ $line->tax_rate }}@if($line->tax_zero_reason_code) · {{ $line->tax_zero_reason_code }}@endif</td>
    <td class="amount-cell">{{ $line->net_total }}</td>
    <td class="amount-cell">{{ $line->tax_total }}</td>
    <td class="amount-cell">{{ $line->gross_total }}</td>
</tr>
@endforeach
</tbody>
<tfoot><tr><th colspan="6">Snapshot Toplamı</th><th class="amount-cell">{{ $revision->net_total }}</th><th class="amount-cell">{{ $revision->tax_total }}</th><th class="amount-cell">{{ $revision->gross_total }} {{ $revision->currency_code }}</th></tr></tfoot>
</table>
</section>

@if($revision->note)<section class="detail-card"><h2>Snapshot Notu</h2><p>{{ $revision->note }}</p></section>@endif
@endsection
