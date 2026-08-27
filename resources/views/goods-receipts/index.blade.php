@extends('layouts.app')

@section('title', 'Mal Kabul')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Mal Kabul</p><h1>Mal Kabul</h1><p>Accepted miktar kullanılabilir stoğa girer; pending/rejected kalite custody olarak ayrı kalır.</p></div>
    <div class="page-actions">@can('goods_receipts.manage')<a class="button-primary" href="{{ route('goods-receipts.create') }}">Yeni Mal Kabul</a>@endcan</div>
</section>

<form method="GET" class="toolbar-form"><input type="search" name="q" value="{{ $search }}" placeholder="Belge, sipariş veya tedarikçi ara"><button type="submit">Ara</button></form>

<section class="statement-table-card"><table class="data-table"><thead><tr><th>Belge</th><th>Tarih</th><th>Tedarikçi</th><th>Kaynak Sipariş</th><th>Durum</th></tr></thead><tbody>
@forelse($receipts as $receipt)
<tr><td><a href="{{ route('goods-receipts.show', $receipt->getKey()) }}">{{ $receipt->number }}</a></td><td>{{ $receipt->receipt_date?->format('d.m.Y') }}</td><td>{{ $receipt->account?->legal_name }}</td><td><a href="{{ route('purchase-orders.show', $receipt->purchase_order_id) }}">{{ $receipt->purchaseOrder?->number }}</a></td><td>{{ $receipt->statusEnum()->label() }}</td></tr>
@empty<tr><td colspan="5">Mal kabul kaydı yok.</td></tr>@endforelse
</tbody></table></section>
{{ $receipts->links() }}
@endsection
