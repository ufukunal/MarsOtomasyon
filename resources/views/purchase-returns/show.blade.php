@extends('layouts.app')

@section('title', 'Satınalma İadesi '.$return->number)

@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">Satınalma / İade</p><h1>{{ $return->number }}</h1><p>{{ $return->purchaseOrder?->number }} · {{ $return->account?->legal_name }} · {{ $return->statusEnum()->label() }}</p></div><div class="page-actions">@can('purchase_returns.manage')@if($return->isDraft())<form method="POST" action="{{ route('purchase-returns.finalize', $return->getKey()) }}">@csrf<button class="button-primary" type="submit">Kesinleştir</button></form>@endif@endcan</div></section>
<section class="detail-card"><div class="form-grid"><div><strong>Tarih</strong><p>{{ $return->return_date?->format('d.m.Y') }}</p></div><div><strong>Para Birimi</strong><p>{{ $return->currency_code }}</p></div><div><strong>Genel Toplam</strong><p>{{ $return->gross_total }} {{ $return->currency_code }}</p></div><div><strong>Not</strong><p>{{ $return->note ?: '—' }}</p></div></div></section>
<section class="statement-table-card"><table class="data-table"><thead><tr><th>Ürün</th><th>Mal Kabul Lineage</th><th>Alış Faturası Lineage</th><th>Miktar</th><th>Net</th><th>Vergi</th><th>Brüt</th></tr></thead><tbody>
@foreach($return->lines as $line)<tr><td>{{ $line->product_code }} — {{ $line->product_name }}</td><td>{{ $line->goodsReceiptLine?->goodsReceipt?->number }} / #{{ $line->goods_receipt_line_id }}</td><td>{{ $line->supplierInvoiceLine?->supplierInvoice?->number }} / #{{ $line->supplier_invoice_line_id }}</td><td>{{ $line->quantity }}</td><td>{{ $line->net_total }}</td><td>{{ $line->tax_total }}</td><td>{{ $line->gross_total }}</td></tr>@endforeach
</tbody></table></section>
@endsection
