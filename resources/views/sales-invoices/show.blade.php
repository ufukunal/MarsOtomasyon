@extends('layouts.app')

@section('title', 'Satış Faturası '.$invoice->number)

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Faturalar</p>
        <h1>{{ $invoice->number }}</h1>
        <p>Taslak fatura source lineage ve hukuki müşteri snapshot'ını taşır. Posting etkileri sonraki M8 dilimlerinde eklenecektir.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('sales-invoices.index') }}">Liste</a>
        @if($invoice->sourceSalesOrder && auth()->user()?->can('sales_orders.view'))
            <a href="{{ route('sales-orders.show', $invoice->source_sales_order_id) }}">Kaynak Sipariş</a>
        @endif
        @if($invoice->sourceDispatch && auth()->user()?->can('dispatches.view'))
            <a href="{{ route('dispatches.show', $invoice->source_dispatch_id) }}">Kaynak İrsaliye</a>
        @endif
    </div>
</section>

<section class="detail-card">
    <div class="form-grid">
        <div><small>Durum</small><strong>{{ $invoice->statusEnum()->label() }}</strong></div>
        <div><small>Mod</small><strong>{{ $invoice->modeEnum()->label() }}</strong></div>
        <div><small>Tarih</small><strong>{{ $invoice->invoice_date?->format('d.m.Y') }}</strong></div>
        <div><small>Para Birimi</small><strong>{{ $invoice->currency_code }}</strong></div>
        <div><small>Kaynak Sipariş</small><strong>{{ $invoice->sourceSalesOrder?->number ?? '—' }}</strong></div>
        <div><small>Kaynak İrsaliye</small><strong>{{ $invoice->sourceDispatch?->number ?? '—' }}</strong></div>
    </div>
</section>

<section class="detail-card">
    <h2>Hukuki Müşteri Snapshot</h2>
    <div class="form-grid">
        <div><small>Ünvan</small><strong>{{ $invoice->customer_legal_name }}</strong></div>
        <div><small>Ticari Ünvan</small><strong>{{ $invoice->customer_trade_name ?? '—' }}</strong></div>
        <div><small>Vergi Kimlik Tipi</small><strong>{{ $invoice->customer_tax_identity_type }}</strong></div>
        <div><small>Vergi No</small><strong>{{ $invoice->customer_tax_number ?? '—' }}</strong></div>
        <div><small>Vergi Dairesi</small><strong>{{ $invoice->customer_tax_office ?? '—' }}</strong></div>
    </div>
</section>

<section class="detail-card">
    <h2>Fatura Adresi Snapshot</h2>
    <p>
        @if($invoice->recipient_name)<strong>{{ $invoice->recipient_name }}</strong><br>@endif
        {{ $invoice->address_line1 }}@if($invoice->address_line2)<br>{{ $invoice->address_line2 }}@endif<br>
        @if($invoice->district){{ $invoice->district }} / @endif{{ $invoice->city }} @if($invoice->postal_code){{ $invoice->postal_code }}@endif · {{ $invoice->country_code }}
    </p>
    <small>Kaynak fatura adresi #{{ $invoice->source_billing_address_id }}. Cari/adres master'ı sonradan değişse bile bu snapshot korunur.</small>
</section>

<section class="statement-table-card">
<table class="data-table">
    <thead><tr><th>#</th><th>Ürün Snapshot</th><th>Açıklama</th><th>Miktar</th><th>Depo / Konum</th><th>Sipariş Satırı</th><th>İrsaliye Satırı</th></tr></thead>
    <tbody>
    @foreach($invoice->lines as $line)
        <tr>
            <td>{{ $line->position }}</td>
            <td>{{ $line->product_code }} — {{ $line->product_name }}</td>
            <td>{{ $line->description ?? '—' }}</td>
            <td>{{ $line->quantity }}</td>
            <td>{{ $line->warehouse?->code }} / {{ $line->location?->code }}</td>
            <td>{{ $line->source_sales_order_line_id ? '#'.$line->source_sales_order_line_id : '—' }}</td>
            <td>{{ $line->source_dispatch_line_id ? '#'.$line->source_dispatch_line_id : '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</section>

@if($invoice->note)<section class="detail-card"><h2>Not</h2><p>{{ $invoice->note }}</p></section>@endif
@endsection
