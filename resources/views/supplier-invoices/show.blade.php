@extends('layouts.app')

@section('title', 'Alış Faturası '.$invoice->number)

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Fatura</p><h1>{{ $invoice->number }}</h1><p>Tedarikçi cari etkisi ve satınalma siparişi faturalama lineage.</p></div>
    <div class="page-actions"><a href="{{ route('supplier-invoices.index') }}">Liste</a>@can('supplier_invoices.manage')@if($invoice->isDraft())<a href="{{ route('supplier-invoices.edit', $invoice->getKey()) }}">Düzenle</a><form method="POST" action="{{ route('supplier-invoices.finalize', $invoice->getKey()) }}">@csrf<button class="button-primary" type="submit">Kesinleştir</button></form>@endif @endcan</div>
</section>

<section class="detail-card"><div class="form-grid">
    <div><small>Tedarikçi</small><strong>{{ $invoice->account?->legal_name }}</strong></div>
    <div><small>Kaynak Sipariş</small><strong><a href="{{ route('purchase-orders.show', $invoice->purchase_order_id) }}">{{ $invoice->purchaseOrder?->number }}</a></strong></div>
    <div><small>Tarih</small><strong>{{ $invoice->invoice_date?->format('d.m.Y') }}</strong></div>
    <div><small>Durum</small><strong>{{ $invoice->statusEnum()->label() }}</strong></div>
    <div><small>Para Birimi</small><strong>{{ $invoice->currency_code }}</strong></div>
    @if($invoice->finalized_at)<div><small>Kesinleşme</small><strong>{{ $invoice->finalized_at->format('d.m.Y H:i') }}</strong></div>@endif
</div></section>

<section class="statement-table-card"><table class="data-table"><thead><tr><th>#</th><th>Ürün</th><th>Miktar</th><th>PO Fatura Kalan</th><th>Birim Fiyat</th><th>KDV</th><th>Net</th><th>Genel</th></tr></thead><tbody>
@foreach($invoice->lines as $line)
<tr><td>{{ $line->position }}</td><td>{{ $line->product_code }} — {{ $line->product_name }}<br><small>{{ $line->description }}</small></td><td>{{ $line->quantity }}</td><td>{{ $line->purchaseOrderLine?->progress?->invoice_remaining_quantity }}</td><td>{{ $line->unit_price }}</td><td>{{ $line->tax_code }} · %{{ $line->tax_rate }}</td><td>{{ $line->net_total }}</td><td>{{ $line->gross_total }}</td></tr>
@endforeach
</tbody></table></section>

<section class="detail-card"><div class="form-grid">
    <div><small>Ara Toplam</small><strong>{{ $invoice->base_net_total }} {{ $invoice->currency_code }}</strong></div>
    <div><small>Satır İskonto</small><strong>{{ $invoice->line_discount_total }} {{ $invoice->currency_code }}</strong></div>
    <div><small>Belge İskonto</small><strong>{{ $invoice->document_discount_total }} {{ $invoice->currency_code }}</strong></div>
    <div><small>Net</small><strong>{{ $invoice->net_total }} {{ $invoice->currency_code }}</strong></div>
    <div><small>KDV</small><strong>{{ $invoice->tax_total }} {{ $invoice->currency_code }}</strong></div>
    <div><small>Genel Toplam</small><strong>{{ $invoice->gross_total }} {{ $invoice->currency_code }}</strong></div>
</div>@if($invoice->note)<p>{{ $invoice->note }}</p>@endif</section>
@endsection
