@extends('layouts.app')

@section('title', 'Satınalma İadeleri')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Satınalma / İadeler</p><h1>Satınalma İadeleri</h1><p>Mal kabul fiziksel lineage ve alış faturası finansal lineage üzerinden stok/cari düzeltmesi.</p></div>
    <div class="page-actions">@can('purchase_returns.manage')<a class="button-primary" href="{{ route('purchase-returns.create') }}">Yeni Satınalma İadesi</a>@endcan</div>
</section>
<section class="detail-card">
<form method="GET" action="{{ route('purchase-returns.index') }}" class="form-grid"><div><label for="q">Ara</label><input id="q" name="q" value="{{ $search }}" placeholder="İade, sipariş veya tedarikçi"></div><div class="page-actions"><button type="submit">Ara</button></div></form>
</section>
<section class="statement-table-card"><table class="data-table"><thead><tr><th>İade</th><th>Tedarikçi</th><th>Kaynak Sipariş</th><th>Tarih</th><th>Durum</th><th>Genel Toplam</th></tr></thead><tbody>
@forelse($returns as $return)
<tr><td><a href="{{ route('purchase-returns.show', $return->getKey()) }}">{{ $return->number }}</a></td><td>{{ $return->account?->legal_name }}</td><td>{{ $return->purchaseOrder?->number }}</td><td>{{ $return->return_date?->format('d.m.Y') }}</td><td>{{ $return->statusEnum()->label() }}</td><td>{{ $return->gross_total }} {{ $return->currency_code }}</td></tr>
@empty<tr><td colspan="6">Satınalma iadesi bulunamadı.</td></tr>@endforelse
</tbody></table></section>
{{ $returns->links() }}
@endsection
