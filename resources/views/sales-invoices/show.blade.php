@extends('layouts.app')

@section('title', 'Satış Faturası '.$invoice->number)

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Faturalar</p>
        <h1>{{ $invoice->number }}</h1>
        <p>Kesinleştirme genel toplamı cari hesaba borç olarak atomik işler; iptal bu hareketi yeni ve tam ters bir ledger kaydıyla geri alır.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('sales-invoices.index') }}">Liste</a>
        @if($invoice->statusEnum() !== \App\Modules\SalesInvoices\Enums\SalesInvoiceStatus::Draft)
            <a href="{{ route('sales-invoices.finalized.show', $invoice->getKey()) }}">Finalized Belge</a>
        @endif
        @if($invoice->sourceSalesOrder && auth()->user()?->can('sales_orders.view'))
            <a href="{{ route('sales-orders.show', $invoice->source_sales_order_id) }}">Kaynak Sipariş</a>
        @endif
        @if($invoice->sourceDispatch && auth()->user()?->can('dispatches.view'))
            <a href="{{ route('dispatches.show', $invoice->source_dispatch_id) }}">Kaynak İrsaliye</a>
        @endif
        @can('sales_invoices.manage')
            @if($invoice->statusEnum() === \App\Modules\SalesInvoices\Enums\SalesInvoiceStatus::Draft)
                <form method="POST" action="{{ route('sales-invoices.finalize', $invoice->getKey()) }}">
                    @csrf
                    <button type="submit">Faturayı Kesinleştir</button>
                </form>
            @elseif($invoice->statusEnum() === \App\Modules\SalesInvoices\Enums\SalesInvoiceStatus::Finalized)
                <form method="POST" action="{{ route('sales-invoices.cancel', $invoice->getKey()) }}">
                    @csrf
                    <button type="submit">Faturayı İptal Et</button>
                </form>
            @endif
        @endcan
    </div>
</section>

<section class="detail-card">
    <div class="form-grid">
        <div><small>Durum</small><strong>{{ $invoice->statusEnum()->label() }}</strong></div>
        <div><small>Kesinleşme</small><strong>{{ $invoice->finalized_at?->format('d.m.Y H:i') ?? '—' }}</strong></div>
        <div><small>İptal</small><strong>{{ $invoice->cancelled_at?->format('d.m.Y H:i') ?? '—' }}</strong></div>
        <div><small>Mod</small><strong>{{ $invoice->modeEnum()->label() }}</strong></div>
        <div><small>Tarih</small><strong>{{ $invoice->invoice_date?->format('d.m.Y') }}</strong></div>
        <div><small>Para Birimi</small><strong>{{ $invoice->currency_code }}</strong></div>
        <div><small>Belge İndirimi</small><strong>%{{ $invoice->document_discount_rate }}</strong></div>
        <div><small>Kaynak Sipariş</small><strong>{{ $invoice->sourceSalesOrder?->number ?? '—' }}</strong></div>
        <div><small>Kaynak İrsaliye</small><strong>{{ $invoice->sourceDispatch?->number ?? '—' }}</strong></div>
    </div>
</section>

<section class="detail-card">
    <h2>Ticari Toplamlar</h2>
    <div class="form-grid">
        <div><small>Baz Net</small><strong>{{ $invoice->base_net_total }} {{ $invoice->currency_code }}</strong></div>
        <div><small>Satır İndirimi</small><strong>{{ $invoice->line_discount_total }} {{ $invoice->currency_code }}</strong></div>
        <div><small>Belge İndirimi</small><strong>{{ $invoice->document_discount_total }} {{ $invoice->currency_code }}</strong></div>
        <div><small>Net</small><strong>{{ $invoice->net_total }} {{ $invoice->currency_code }}</strong></div>
        <div><small>KDV</small><strong>{{ $invoice->tax_total }} {{ $invoice->currency_code }}</strong></div>
        <div><small>Genel Toplam</small><strong>{{ $invoice->gross_total }} {{ $invoice->currency_code }}</strong></div>
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
    <thead><tr><th>#</th><th>Ürün</th><th>Miktar</th><th>Fiyat</th><th>İndirim</th><th>KDV</th><th>Net</th><th>KDV Tutarı</th><th>Brüt</th><th>Depo / Konum</th><th>Lineage</th></tr></thead>
    <tbody>
    @foreach($invoice->lines as $line)
        <tr>
            <td>{{ $line->position }}</td>
            <td>{{ $line->product_code }} — {{ $line->product_name }}@if($line->description)<br><small>{{ $line->description }}</small>@endif</td>
            <td>{{ $line->quantity }}</td>
            <td>{{ $line->unit_price }} / {{ strtoupper($line->price_basis->value) }}</td>
            <td>%{{ $line->line_discount_rate }}<br><small>Satır {{ $line->line_discount_net }} / Belge {{ $line->document_discount_net }}</small></td>
            <td>{{ $line->tax_code }} · %{{ $line->tax_rate }}@if($line->tax_zero_reason_code)<br><small>{{ $line->tax_zero_reason_code }}</small>@endif</td>
            <td>{{ $line->net_total }}</td>
            <td>{{ $line->tax_total }}</td>
            <td>{{ $line->gross_total }}</td>
            <td>{{ $line->warehouse?->code }} / {{ $line->location?->code }}</td>
            <td>SO {{ $line->source_sales_order_line_id ? '#'.$line->source_sales_order_line_id : '—' }}<br>DSP {{ $line->source_dispatch_line_id ? '#'.$line->source_dispatch_line_id : '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</section>

@if($invoice->note)<section class="detail-card"><h2>Not</h2><p>{{ $invoice->note }}</p></section>@endif
@endsection
