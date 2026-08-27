@extends('layouts.app')

@section('title', 'Satınalma Siparişleri')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Sipariş</p><h1>Satınalma Siparişleri</h1><p>Tedarikçi siparişleri; mal kabul ve alış faturası kalanları birbirinden bağımsız izlenir.</p></div>
    <div class="page-actions">@can('purchase_orders.manage')<a class="button-primary" href="{{ route('purchase-orders.create') }}">Yeni Satınalma Siparişi</a>@endcan</div>
</section>

<form method="get" class="detail-card">
    <div class="form-grid"><label>Ara<input name="q" value="{{ $search }}" placeholder="Sipariş no veya tedarikçi"></label></div>
    <div class="page-actions"><button class="button-secondary" type="submit">Filtrele</button></div>
</form>

<section class="statement-table-card">
<table class="data-table"><thead><tr><th>No</th><th>Tarih</th><th>Tedarikçi</th><th>Durum</th><th>Net</th><th>KDV</th><th>Genel</th></tr></thead><tbody>
@forelse($orders as $order)
<tr>
    <td><a href="{{ route('purchase-orders.show', $order->getKey()) }}">{{ $order->number }}</a></td>
    <td>{{ $order->order_date?->format('d.m.Y') }}</td>
    <td>{{ $order->account?->legal_name }}</td>
    <td>{{ $order->statusEnum()->label() }}</td>
    <td>{{ $order->net_total }} {{ $order->currency_code }}</td>
    <td>{{ $order->tax_total }} {{ $order->currency_code }}</td>
    <td>{{ $order->gross_total }} {{ $order->currency_code }}</td>
</tr>
@empty<tr><td colspan="7">Satınalma siparişi bulunamadı.</td></tr>@endforelse
</tbody></table>
</section>
{{ $orders->links() }}
@endsection
