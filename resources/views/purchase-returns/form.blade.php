@extends('layouts.app')

@section('title', 'Yeni Satınalma İadesi')

@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">Satınalma / İadeler</p><h1>Yeni Satınalma İadesi</h1><p>Fiziksel kaynak kesinleşmiş mal kabul, finansal kaynak kesinleşmiş alış faturasıdır.</p></div></section>
<section class="detail-card">
<form method="GET" action="{{ route('purchase-returns.create') }}" class="form-grid">
<div><label for="purchase_order_id">Satınalma Siparişi</label><select id="purchase_order_id" name="purchase_order_id"><option value="">Seçiniz</option>@foreach($orders as $order)<option value="{{ $order->getKey() }}" @selected($selectedOrder?->getKey() === $order->getKey())>{{ $order->number }} — {{ $order->account?->legal_name }}</option>@endforeach</select></div>
<div class="page-actions"><button type="submit">Kaynakları Getir</button></div>
</form>
</section>
@if($selectedOrder)
<form method="POST" action="{{ route('purchase-returns.store') }}">@csrf
<input type="hidden" name="purchase_order_id" value="{{ $selectedOrder->getKey() }}">
<section class="detail-card"><div class="form-grid"><div><label for="series_code">Seri</label><input id="series_code" name="series_code" value="{{ old('series_code', 'default') }}"></div><div><label for="return_date">İade Tarihi</label><input id="return_date" type="date" name="return_date" value="{{ old('return_date', now()->format('Y-m-d')) }}" required></div><div><label for="note">Not</label><input id="note" name="note" value="{{ old('note') }}"></div></div></section>
<section class="statement-table-card"><table class="data-table"><thead><tr><th>Finansal Kaynak</th><th>Ürün</th><th>Fiziksel Mal Kabul Kaynağı</th><th>İade Miktarı</th></tr></thead><tbody>
@php($row = 0)
@foreach($invoiceLines as $invoiceLine)
@php($compatible = $receiptLines->filter(fn($receiptLine) => (int) $receiptLine->purchase_order_line_id === (int) $invoiceLine->purchase_order_line_id && (int) $receiptLine->product_id === (int) $invoiceLine->product_id))
@if($compatible->isNotEmpty())
<tr><td>{{ $invoiceLine->supplierInvoice?->number }} / #{{ $invoiceLine->getKey() }}<input type="hidden" name="lines[{{ $row }}][supplier_invoice_line_id]" value="{{ $invoiceLine->getKey() }}"></td><td>{{ $invoiceLine->product_code }} — {{ $invoiceLine->product_name }}</td><td><select name="lines[{{ $row }}][goods_receipt_line_id]" required>@foreach($compatible as $receiptLine)<option value="{{ $receiptLine->getKey() }}">{{ $receiptLine->goodsReceipt?->number }} / #{{ $receiptLine->getKey() }} — kabul {{ $acceptedByReceiptLine->get($receiptLine->getKey(), '0.000000') }}</option>@endforeach</select></td><td><input name="lines[{{ $row }}][quantity]" value="{{ old('lines.'.$row.'.quantity', '0') }}" inputmode="decimal"></td></tr>
@php($row++)
@endif
@endforeach
@if($row === 0)<tr><td colspan="4">Bu sipariş için eşleşen kesinleşmiş mal kabul ve alış faturası lineage bulunamadı.</td></tr>@endif
</tbody></table></section>
@if($row > 0)<div class="page-actions"><button class="button-primary" type="submit">İade Taslağı Oluştur</button></div>@endif
</form>
@endif
@endsection
