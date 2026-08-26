@extends('layouts.app')

@section('title', 'İrsaliye '.$dispatch->number)

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Satış / İrsaliye</p><h1>{{ $dispatch->number }}</h1><p>Satış siparişi kaynaklı taslak irsaliye. M7.2 miktar contract'ı sipariş ve diğer taslaklarla ortak kapasiteyi gösterir.</p></div>
    <div class="page-actions">
        <a href="{{ route('dispatches.index') }}">Liste</a>
        @can('sales_orders.view')<a href="{{ route('sales-orders.show', $dispatch->sales_order_id) }}">Kaynak Sipariş</a>@endcan
    </div>
</section>

<section class="detail-card">
    <div class="form-grid">
        <div><small>Durum</small><strong>{{ $dispatch->statusEnum()->label() }}</strong></div>
        <div><small>Tarih</small><strong>{{ $dispatch->dispatch_date?->format('d.m.Y') }}</strong></div>
        <div><small>Cari</small><strong>{{ $dispatch->account?->legal_name }}</strong></div>
        <div><small>Kaynak Sipariş</small><strong>{{ $dispatch->salesOrder?->number }}</strong></div>
        <div><small>Taşıyıcı</small><strong>{{ $dispatch->carrier_name ?? '—' }}</strong></div>
        <div><small>Servis</small><strong>{{ $dispatch->carrier_service ?? '—' }}</strong></div>
        <div><small>Takip No</small><strong>{{ $dispatch->tracking_number ?? '—' }}</strong></div>
    </div>
</section>

<section class="detail-card">
    <h2>Sevk Adresi Snapshot</h2>
    <p>
        @if($dispatch->recipient_name)<strong>{{ $dispatch->recipient_name }}</strong><br>@endif
        {{ $dispatch->address_line1 }}@if($dispatch->address_line2)<br>{{ $dispatch->address_line2 }}@endif<br>
        @if($dispatch->district){{ $dispatch->district }} / @endif{{ $dispatch->city }} @if($dispatch->postal_code){{ $dispatch->postal_code }}@endif · {{ $dispatch->country_code }}
    </p>
    <small>Kaynak adres #{{ $dispatch->source_address_id }}. Snapshot, cari adresi sonradan değişse bile irsaliye üzerinde korunur.</small>
</section>

<section class="statement-table-card">
<table class="data-table"><thead><tr><th>#</th><th>Sipariş Satırı</th><th>Ürün Snapshot</th><th>Açıklama</th><th>Depo / Konum</th><th>Sipariş</th><th>Bu İrsaliye</th><th>Sevk/Taslak Toplamı</th><th>Kalan</th></tr></thead><tbody>
@foreach($dispatch->lines as $line)
@php($capacity = $capacities->get($line->sales_order_line_id))
<tr>
    <td>{{ $line->position }}</td>
    <td>#{{ $line->sales_order_line_id }}</td>
    <td>{{ $line->product_code }} — {{ $line->product_name }}</td>
    <td>{{ $line->description }}</td>
    <td>{{ $line->warehouse?->code ?? '—' }} / {{ $line->location?->code ?? '—' }}</td>
    <td>{{ $capacity?->ordered_quantity ?? $line->salesOrderLine?->quantity ?? '—' }}</td>
    <td>{{ $line->quantity }}</td>
    <td>{{ $capacity?->previous_quantity ?? '—' }}</td>
    <td>{{ $capacity?->remaining_quantity ?? '—' }}</td>
</tr>
@endforeach
</tbody></table>
</section>

@if($dispatch->note)<section class="detail-card"><h2>Not</h2><p>{{ $dispatch->note }}</p></section>@endif
@endsection
