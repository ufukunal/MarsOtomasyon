@extends('layouts.app')

@section('title', 'Teklif '.$quote->number)

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Satış / Teklif</p><h1>{{ $quote->number }}</h1><p>{{ $quote->account->legal_name }} · {{ $quote->statusEnum()->label() }}</p></div>
    <div class="page-actions">
        <a href="{{ route('quotes.index') }}" data-workspace-link>Teklifler</a>
        @can('quotes.manage')
            @if($quote->isDraft())
                <a class="button-primary" href="{{ route('quotes.edit', $quote->getKey()) }}" data-workspace-link>Düzenle</a>
                <form method="post" action="{{ route('quotes.cancel', $quote->getKey()) }}">@csrf<button type="submit">İptal Et</button></form>
            @endif
        @endcan
    </div>
</section>

<section class="detail-card">
    <dl class="detail-grid">
        <div><dt>Teklif Tarihi</dt><dd>{{ $quote->quote_date->format('d.m.Y') }}</dd></div>
        <div><dt>Geçerlilik</dt><dd>{{ $quote->valid_until?->format('d.m.Y') ?? '—' }}</dd></div>
        <div><dt>Para Birimi</dt><dd>{{ $quote->currency_code }}</dd></div>
        <div><dt>Belge İskonto</dt><dd>%{{ $quote->document_discount_rate }}</dd></div>
    </dl>
</section>

<section class="detail-card statement-table-card">
<table class="data-table">
<thead><tr><th>#</th><th>Ürün</th><th>Açıklama</th><th class="amount-cell">Miktar</th><th class="amount-cell">Birim Fiyat</th><th>KDV</th><th class="amount-cell">Net</th><th class="amount-cell">Vergi</th><th class="amount-cell">Toplam</th></tr></thead>
<tbody>@foreach($quote->lines as $line)<tr>
<td>{{ $line->position }}</td><td>{{ $line->product_code }}</td><td>{{ $line->description }}</td><td class="amount-cell">{{ $line->quantity }}</td><td class="amount-cell">{{ $line->unit_price }}</td><td>%{{ $line->tax_rate }}@if($line->tax_zero_reason_code) · {{ $line->tax_zero_reason_code }}@endif</td><td class="amount-cell">{{ $line->net_total }}</td><td class="amount-cell">{{ $line->tax_total }}</td><td class="amount-cell">{{ $line->gross_total }}</td>
</tr>@endforeach</tbody>
<tfoot><tr><th colspan="6">Toplam</th><th class="amount-cell">{{ $quote->net_total }}</th><th class="amount-cell">{{ $quote->tax_total }}</th><th class="amount-cell">{{ $quote->gross_total }} {{ $quote->currency_code }}</th></tr></tfoot>
</table>
</section>
@if($quote->note)<section class="detail-card"><h2>Not</h2><p>{{ $quote->note }}</p></section>@endif
@endsection
