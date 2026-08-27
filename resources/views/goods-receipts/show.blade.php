@extends('layouts.app')

@section('title', 'Mal Kabul '.$receipt->number)

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Mal Kabul</p><h1>{{ $receipt->number }}</h1><p>Fiziksel teslim custody dağılımı ve accepted Stock IN authority.</p></div>
    <div class="page-actions"><a href="{{ route('goods-receipts.index') }}">Liste</a>@can('goods_receipts.manage')@if($receipt->isDraft())<a href="{{ route('goods-receipts.edit', $receipt->getKey()) }}">Düzenle</a><form method="POST" action="{{ route('goods-receipts.finalize', $receipt->getKey()) }}">@csrf<button class="button-primary" type="submit">Kesinleştir</button></form>@endif @endcan</div>
</section>

<section class="detail-card"><div class="form-grid"><div><small>Tedarikçi</small><strong>{{ $receipt->account?->legal_name }}</strong></div><div><small>Kaynak Sipariş</small><strong><a href="{{ route('purchase-orders.show', $receipt->purchase_order_id) }}">{{ $receipt->purchaseOrder?->number }}</a></strong></div><div><small>Tarih</small><strong>{{ $receipt->receipt_date?->format('d.m.Y') }}</strong></div><div><small>Durum</small><strong>{{ $receipt->statusEnum()->label() }}</strong></div>@if($receipt->finalized_at)<div><small>Kesinleşme</small><strong>{{ $receipt->finalized_at->format('d.m.Y H:i') }}</strong></div>@endif</div></section>

<section class="statement-table-card"><table class="data-table"><thead><tr><th>#</th><th>Ürün</th><th>Depo/Lokasyon</th><th>Fiziksel</th><th>Accepted</th><th>Pending</th><th>Rejected</th><th>Prov. Birim Maliyet</th><th>PO Kalan</th></tr></thead><tbody>
@foreach($receipt->lines as $line)<tr><td>{{ $line->position }}</td><td>{{ $line->product_code }} — {{ $line->product_name }}</td><td>{{ $line->warehouse?->code }} / {{ $line->location?->code }}</td><td>{{ $line->received_quantity }}</td><td>{{ $line->accepted_quantity }}</td><td>{{ $line->pending_quantity }}</td><td>{{ $line->rejected_quantity }}</td><td>{{ $line->provisional_unit_cost }}</td><td>{{ $line->purchaseOrderLine?->progress?->receive_remaining_quantity }}</td></tr>@endforeach
</tbody></table></section>
@if($receipt->note)<section class="detail-card"><p>{{ $receipt->note }}</p></section>@endif
@endsection
