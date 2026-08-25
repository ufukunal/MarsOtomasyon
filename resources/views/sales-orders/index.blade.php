@extends('layouts.app')

@section('title', 'Satış Siparişleri')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Satış / Sipariş</p><h1>Satış Siparişleri</h1><p>Manuel taslaklar ve tekliften oluşan immutable siparişler aynı listede, kaynak bilgisiyle izlenir.</p></div>
    @can('sales_orders.manage')<a class="button-primary" href="{{ route('sales-orders.create') }}">Yeni Sipariş</a>@endcan
</section>

<form method="get" class="detail-card">
    <div class="form-grid">
        <label>Ara<input name="q" value="{{ $search }}" placeholder="Sipariş no veya cari"></label>
        <label>Kaynak<select name="source"><option value="all" @selected($sourceFilter === 'all')>Tümü</option><option value="manual" @selected($sourceFilter === 'manual')>Manuel</option><option value="quote" @selected($sourceFilter === 'quote')>Teklif</option></select></label>
    </div>
    <div class="page-actions"><button class="button-secondary" type="submit">Filtrele</button></div>
</form>

<section class="statement-table-card">
<table class="data-table"><thead><tr><th>No</th><th>Tarih</th><th>Cari</th><th>Kaynak</th><th>Durum</th><th>Net</th><th>KDV</th><th>Genel</th></tr></thead><tbody>
@forelse($orders as $order)
<tr>
    <td><a href="{{ route('sales-orders.show', $order->getKey()) }}">{{ $order->number }}</a></td>
    <td>{{ $order->order_date?->format('d.m.Y') }}</td>
    <td>{{ $order->account?->legal_name }}</td>
    <td>{{ $order->isManual() ? 'Manuel' : 'Teklif' }}</td>
    <td>{{ $order->statusEnum()->label() }}</td>
    <td>{{ $order->net_total }} {{ $order->currency_code }}</td>
    <td>{{ $order->tax_total }} {{ $order->currency_code }}</td>
    <td>{{ $order->gross_total }} {{ $order->currency_code }}</td>
</tr>
@empty<tr><td colspan="8">Sipariş bulunamadı.</td></tr>@endforelse
</tbody></table>
</section>
{{ $orders->links() }}
@endsection
