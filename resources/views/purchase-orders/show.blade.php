@extends('layouts.app')

@section('title', 'Satınalma '.$order->number)

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Sipariş</p><h1>{{ $order->number }}</h1><p>Tedarikçi siparişi snapshotı; mal kabul ve alış faturası kalanları ayrı authority olarak izlenir.</p></div>
    <div class="page-actions">
        <a href="{{ route('purchase-orders.index') }}">Liste</a>
        @can('purchase_orders.manage')@if($order->isDraft() && (int) $order->progress_effects_count === 0)<a class="button-primary" href="{{ route('purchase-orders.edit', $order->getKey()) }}">Düzenle</a>@endif @endcan
    </div>
</section>

<section class="detail-card"><div class="form-grid">
    <div><small>Tedarikçi</small><strong>{{ $order->account?->legal_name }}</strong></div>
    <div><small>Tarih</small><strong>{{ $order->order_date?->format('d.m.Y') }}</strong></div>
    <div><small>Durum</small><strong>{{ $order->statusEnum()->label() }}</strong></div>
    <div><small>Para Birimi</small><strong>{{ $order->currency_code }}</strong></div>
    <div><small>Belge İskonto</small><strong>%{{ $order->document_discount_rate }}</strong></div>
</div></section>

<section class="statement-table-card">
<table class="data-table"><thead><tr><th>#</th><th>Ürün Snapshot</th><th>Açıklama</th><th>Sipariş</th><th>Kabul</th><th>Kabul Kalan</th><th>Fatura</th><th>Fatura Kalan</th><th>Birim Fiyat</th><th>KDV</th><th>Net</th><th>Genel</th></tr></thead><tbody>
@foreach($order->lines as $line)
<tr>
    <td>{{ $line->position }}</td><td>{{ $line->product_code }} — {{ $line->product_name }}</td><td>{{ $line->description }}</td>
    <td>{{ $line->progress?->ordered_quantity ?? $line->quantity }}</td>
    <td>{{ $line->progress?->net_received_quantity ?? '0.000000' }}</td>
    <td>{{ $line->progress?->receive_remaining_quantity ?? $line->quantity }}</td>
    <td>{{ $line->progress?->net_invoiced_quantity ?? '0.000000' }}</td>
    <td>{{ $line->progress?->invoice_remaining_quantity ?? $line->quantity }}</td>
    <td>{{ $line->unit_price }}</td>
    <td>{{ $line->tax_code }} · %{{ $line->tax_rate }}@if($line->tax_is_zeroed)<br><small>KDV Sıfırlandı</small>@endif @if($line->tax_zero_reason_code)<br><small>Neden: {{ $line->tax_zero_reason_code }}</small>@endif</td>
    <td>{{ $line->net_total }}</td><td>{{ $line->gross_total }}</td>
</tr>
@endforeach
</tbody></table>
</section>

<section class="detail-card"><div class="form-grid">
    <div><small>Ara Toplam</small><strong>{{ $order->base_net_total }} {{ $order->currency_code }}</strong></div>
    <div><small>Satır İskonto</small><strong>{{ $order->line_discount_total }} {{ $order->currency_code }}</strong></div>
    <div><small>Belge İskonto</small><strong>{{ $order->document_discount_total }} {{ $order->currency_code }}</strong></div>
    <div><small>Net</small><strong>{{ $order->net_total }} {{ $order->currency_code }}</strong></div>
    <div><small>KDV</small><strong>{{ $order->tax_total }} {{ $order->currency_code }}</strong></div>
    <div><small>Genel Toplam</small><strong>{{ $order->gross_total }} {{ $order->currency_code }}</strong></div>
</div>@if($order->note)<p>{{ $order->note }}</p>@endif</section>
@endsection
