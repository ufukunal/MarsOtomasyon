@extends('layouts.app')

@section('title', 'İadeler / RMA')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Satış / İadeler</p><h1>İadeler / RMA</h1><p>Kesinleşmiş satış faturası üzerinden kısmi iade, fiziksel kabul, müşteri cari kredisi ve stok dönüşü.</p></div>
    <div class="page-actions">@can('sales_returns.manage')<a class="button-primary" href="{{ route('returns.create') }}">Yeni RMA</a>@endcan</div>
</section>
<section class="detail-card">
<form method="GET" action="{{ route('returns.index') }}" class="form-grid"><div><label for="q">Ara</label><input id="q" name="q" value="{{ $search }}" placeholder="RMA, fatura veya müşteri"></div><div class="page-actions"><button type="submit">Ara</button></div></form>
</section>
<section class="statement-table-card"><table class="data-table"><thead><tr><th>RMA</th><th>Müşteri</th><th>Kaynak Fatura</th><th>Tarih</th><th>Durum</th><th>Talep</th><th>Kredi</th></tr></thead><tbody>
@forelse($returns as $return)
<tr><td><a href="{{ route('returns.show', $return->getKey()) }}">{{ $return->number }}</a></td><td>{{ $return->account?->legal_name }}</td><td>{{ $return->salesInvoice?->number }}</td><td>{{ $return->return_date?->format('d.m.Y') }}</td><td>{{ $return->statusEnum()->label() }}</td><td>{{ $return->requested_gross_total }} {{ $return->currency_code }}</td><td>{{ $return->credited_gross_total }} {{ $return->currency_code }}</td></tr>
@empty<tr><td colspan="7">Satış iadesi / RMA bulunamadı.</td></tr>@endforelse
</tbody></table></section>
{{ $returns->links() }}
@endsection
