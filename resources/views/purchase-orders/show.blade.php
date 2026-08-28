@extends('layouts.app')

@section('title', 'Satınalma '.$order->number)

@section('app-content')
@php
    $canCloseOrder = $order->isOpen() && $order->lines->every(function ($line): bool {
        $receiveRemaining = (float) ($line->progress?->receive_remaining_quantity ?? $line->quantity);
        $invoiceRemaining = (float) ($line->progress?->invoice_remaining_quantity ?? $line->quantity);
        return $receiveRemaining <= 0 && $invoiceRemaining <= 0;
    });
@endphp
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Sipariş</p><h1>{{ $order->number }}</h1><p>Tedarikçi siparişi snapshotı; taslak onaylanıp açıldıktan sonra mal kabul ve alış faturası authority akışı başlar.</p></div>
    <div class="page-actions">
        <a href="{{ route('purchase-orders.index') }}">Liste</a>
        @if($order->isOpen())
            @can('goods_receipts.manage')<a href="{{ route('goods-receipts.create', ['purchase_order_id' => $order->getKey()]) }}">Mal Kabul Oluştur</a>@endcan
            @can('supplier_invoices.manage')<a href="{{ route('supplier-invoices.create', ['purchase_order_id' => $order->getKey()]) }}">Alış Faturası Oluştur</a>@endcan
        @endif
        @can('purchase_orders.manage')
            @if($order->isDraft() && (int) $order->progress_effects_count === 0)
                <a href="{{ route('purchase-orders.edit', $order->getKey()) }}">Düzenle</a>
                <form method="POST" action="{{ route('purchase-orders.open', $order->getKey()) }}" class="inline-form">@csrf<button class="button-primary" type="submit">Siparişi Aç</button></form>
            @elseif($canCloseOrder)
                <form method="POST" action="{{ route('purchase-orders.close', $order->getKey()) }}" class="inline-form">@csrf<button class="button-primary" type="submit">Siparişi Kapat</button></form>
            @endif
        @endcan
    </div>
</section>

<section class="detail-card"><div class="form-grid">
    <div><small>Tedarikçi</small><strong>{{ $order->account?->legal_name }}</strong></div>
    <div><small>Tarih</small><strong>{{ $order->order_date?->format('d.m.Y') }}</strong></div>
    <div><small>Durum</small><strong>{{ $order->statusEnum()->label() }}</strong></div>
    <div><small>Para Birimi</small><strong>{{ $order->currency_code }}</strong></div>
    <div><small>Belge İskonto</small><strong>%{{ $order->document_discount_rate }}</strong></div>
    @if($order->opened_at)<div><small>Açılış</small><strong>{{ $order->opened_at->format('d.m.Y H:i') }}</strong></div>@endif
    @if($order->closed_at)<div><small>Kapanış</small><strong>{{ $order->closed_at->format('d.m.Y H:i') }}</strong></div>@endif
</div></section>

<section class="statement-table-card">
<table class="data-table"><thead><tr><th>#</th><th>Ürün Snapshot</th><th>Açıklama</th><th>Sipariş</th><th>İptal</th><th>Kabul</th><th>Kabul Kalan</th><th>Fatura</th><th>Fatura Kalan</th><th>Birim Fiyat</th><th>KDV</th><th>Net</th><th>Genel</th><th>Açık Miktar İşlemi</th></tr></thead><tbody>
@foreach($order->lines as $line)
@php
    $receiveRemaining = (string) ($line->progress?->receive_remaining_quantity ?? $line->quantity);
    $invoiceRemaining = (string) ($line->progress?->invoice_remaining_quantity ?? $line->quantity);
    $canCancelQuantity = $order->isOpen() && (float) $receiveRemaining > 0 && (float) $invoiceRemaining > 0;
@endphp
<tr>
    <td>{{ $line->position }}</td><td>{{ $line->product_code }} — {{ $line->product_name }}</td><td>{{ $line->description }}</td>
    <td>{{ $line->progress?->ordered_quantity ?? $line->quantity }}</td>
    <td>{{ $line->progress?->cancelled_quantity ?? '0.000000' }}</td>
    <td>{{ $line->progress?->net_received_quantity ?? '0.000000' }}</td>
    <td>{{ $receiveRemaining }}</td>
    <td>{{ $line->progress?->net_invoiced_quantity ?? '0.000000' }}</td>
    <td>{{ $invoiceRemaining }}</td>
    <td>{{ $line->unit_price }}</td>
    <td>{{ $line->tax_code }} · %{{ $line->tax_rate }}@if($line->tax_is_zeroed)<br><small>KDV Sıfırlandı</small>@endif @if($line->tax_zero_reason_code)<br><small>Neden: {{ $line->tax_zero_reason_code }}</small>@endif</td>
    <td>{{ $line->net_total }}</td><td>{{ $line->gross_total }}</td>
    <td>
        @can('purchase_orders.manage')
            @if($canCancelQuantity)
                <form method="POST" action="{{ route('purchase-orders.lines.cancel', [$order->getKey(), $line->getKey()]) }}" class="inline-form">
                    @csrf
                    <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="number" name="quantity" min="0.000001" step="0.000001" required placeholder="Miktar">
                    <button type="submit">Miktar İptal Et</button>
                </form>
                <small>En fazla hem kabul hem fatura kalanını aşmayacak miktar iptal edilebilir.</small>
            @else
                <small>İptal edilebilir açık miktar yok.</small>
            @endif
        @else
            <small>—</small>
        @endcan
    </td>
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
