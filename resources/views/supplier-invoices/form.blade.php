@extends('layouts.app')

@section('title', $invoice ? 'Alış Faturası Düzenle' : 'Yeni Alış Faturası')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Fatura</p><h1>{{ $invoice ? $invoice->number : 'Yeni Alış Faturası' }}</h1><p>Fiyat/vergi snapshotı kaynak satınalma siparişinden server-side hesaplanır.</p></div>
    <div class="page-actions"><a href="{{ $invoice ? route('supplier-invoices.show', $invoice->getKey()) : route('supplier-invoices.index') }}">Vazgeç</a></div>
</section>

@if($errors->any())<section class="detail-card"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

<form method="POST" action="{{ $invoice ? route('supplier-invoices.update', $invoice->getKey()) : route('supplier-invoices.store') }}">
@csrf
@if($invoice) @method('PUT') @endif
<section class="detail-card"><div class="form-grid">
    <div><label>Kaynak Satınalma Siparişi</label>
        @if($invoice)
            <input type="hidden" name="purchase_order_id" value="{{ $invoice->purchase_order_id }}"><strong>{{ $selectedOrder?->number }} — {{ $selectedOrder?->account?->legal_name }}</strong>
        @else
            <select name="purchase_order_id" onchange="if(this.value){window.location='{{ route('supplier-invoices.create') }}?purchase_order_id='+encodeURIComponent(this.value)}">
                <option value="">Sipariş seçin</option>
                @foreach($orders as $order)<option value="{{ $order->getKey() }}" @selected($selectedOrder && $selectedOrder->getKey() === $order->getKey())>{{ $order->number }} — {{ $order->account?->legal_name }}</option>@endforeach
            </select>
        @endif
    </div>
    @unless($invoice)<div><label for="series_code">Seri</label><input id="series_code" name="series_code" value="{{ old('series_code', 'default') }}"></div>@endunless
    <div><label for="invoice_date">Fatura Tarihi</label><input id="invoice_date" type="date" name="invoice_date" value="{{ old('invoice_date', $invoice?->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required></div>
    @if($selectedOrder)<div><small>Para Birimi</small><strong>{{ $selectedOrder->currency_code }}</strong></div><div><small>Belge İskonto</small><strong>%{{ $selectedOrder->document_discount_rate }}</strong></div>@endif
</div></section>

@if($selectedOrder)
<section class="statement-table-card"><table class="data-table"><thead><tr><th>#</th><th>Ürün</th><th>Sipariş</th><th>Faturalanan</th><th>Fatura Kalan</th><th>Bu Fatura</th><th>Birim Fiyat</th><th>KDV</th></tr></thead><tbody>
@foreach($selectedOrder->lines as $line)
@php($existing = $existingLines->get($line->getKey()))
<tr>
    <td>{{ $line->position }}<input type="hidden" name="lines[{{ $loop->index }}][purchase_order_line_id]" value="{{ $line->getKey() }}"></td>
    <td>{{ $line->product_code }} — {{ $line->product_name }}</td>
    <td>{{ $line->quantity }}</td>
    <td>{{ $line->progress?->net_invoiced_quantity ?? '0.000000' }}</td>
    <td>{{ $line->progress?->invoice_remaining_quantity ?? $line->quantity }}</td>
    <td><input name="lines[{{ $loop->index }}][quantity]" value="{{ old('lines.'.$loop->index.'.quantity', $existing?->quantity ?? '0') }}" inputmode="decimal"></td>
    <td>{{ $line->unit_price }}</td><td>{{ $line->tax_code }} · %{{ $line->tax_rate }}</td>
</tr>
@endforeach
</tbody></table></section>
<section class="detail-card"><label for="note">Not</label><textarea id="note" name="note" rows="4">{{ old('note', $invoice?->note) }}</textarea><div class="page-actions"><button class="button-primary" type="submit">{{ $invoice ? 'Taslağı Güncelle' : 'Taslak Oluştur' }}</button></div></section>
@else
<section class="detail-card"><p>Fatura satırlarını hazırlamak için önce kaynak satınalma siparişi seçin.</p></section>
@endif
</form>
@endsection
