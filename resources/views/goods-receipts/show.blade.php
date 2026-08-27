@extends('layouts.app')

@section('title', 'Mal Kabul '.$receipt->number)

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Mal Kabul</p><h1>{{ $receipt->number }}</h1><p>Fiziksel teslim custody dağılımı, kalite sınıflandırması, accepted Stock IN authority ve gerçekleşen maliyet farkı yönetimi.</p></div>
    <div class="page-actions"><a href="{{ route('goods-receipts.index') }}">Liste</a>@can('goods_receipts.manage')@if($receipt->isDraft())<a href="{{ route('goods-receipts.edit', $receipt->getKey()) }}">Düzenle</a><form method="POST" action="{{ route('goods-receipts.finalize', $receipt->getKey()) }}">@csrf<button class="button-primary" type="submit">Kesinleştir</button></form>@endif @endcan</div>
</section>

<section class="detail-card"><div class="form-grid"><div><small>Tedarikçi</small><strong>{{ $receipt->account?->legal_name }}</strong></div><div><small>Kaynak Sipariş</small><strong><a href="{{ route('purchase-orders.show', $receipt->purchase_order_id) }}">{{ $receipt->purchaseOrder?->number }}</a></strong></div><div><small>Tarih</small><strong>{{ $receipt->receipt_date?->format('d.m.Y') }}</strong></div><div><small>Durum</small><strong>{{ $receipt->statusEnum()->label() }}</strong></div>@if($receipt->finalized_at)<div><small>Kesinleşme</small><strong>{{ $receipt->finalized_at->format('d.m.Y H:i') }}</strong></div>@endif</div></section>

<section class="statement-table-card"><table class="data-table"><thead><tr><th>#</th><th>Ürün</th><th>Depo/Lokasyon</th><th>Fiziksel</th><th>Accepted</th><th>Pending</th><th>Rejected</th><th>Prov. Birim Maliyet</th><th>PO Kalan</th><th>Kalite İşlemi</th></tr></thead><tbody>
@foreach($receipt->lines as $line)
@php($quality = $qualityByLine->get((int) $line->getKey()))
<tr>
    <td>{{ $line->position }}</td>
    <td>{{ $line->product_code }} — {{ $line->product_name }}</td>
    <td>{{ $line->warehouse?->code }} / {{ $line->location?->code }}</td>
    <td>{{ $quality?->received_quantity ?? $line->received_quantity }}</td>
    <td>{{ $quality?->accepted_quantity ?? $line->accepted_quantity }}</td>
    <td>{{ $quality?->pending_quantity ?? $line->pending_quantity }}</td>
    <td>{{ $quality?->rejected_quantity ?? $line->rejected_quantity }}</td>
    <td>{{ $line->provisional_unit_cost }}</td>
    <td>{{ $line->purchaseOrderLine?->progress?->receive_remaining_quantity }}</td>
    <td>
        @can('goods_receipts.manage')
            @if(!$receipt->isDraft() && $quality !== null && (float) $quality->pending_quantity > 0)
                <form method="POST" action="{{ route('goods-receipts.quality', $receipt->getKey()) }}" class="compact-form">
                    @csrf
                    <input type="hidden" name="goods_receipt_line_id" value="{{ $line->getKey() }}">
                    <select name="disposition" required>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input type="number" name="quantity" min="0.000001" max="{{ $quality->pending_quantity }}" step="0.000001" placeholder="Miktar" required>
                    <input type="text" name="note" maxlength="1000" placeholder="Kalite notu">
                    <button type="submit">İşle</button>
                </form>
            @else
                <span>—</span>
            @endif
        @else
            <span>—</span>
        @endcan
    </td>
</tr>
@endforeach
</tbody></table></section>

@can('goods_receipts.manage')
@if(!$receipt->isDraft())
<section class="detail-card">
    <h2>Gerçekleşen / Landed Cost Farkı</h2>
    <p>Bu işlem ikinci bir stok girişi oluşturmaz. Miktar sabit kalır; fark, mevcut on-hand miktar ile tüketilmiş miktar arasında ayrılır ve moving-average stok değeri yeniden hesaplanır.</p>
    <form method="POST" action="{{ route('goods-receipts.cost-adjustments.store', $receipt->getKey()) }}" class="form-grid">
        @csrf
        <label>Mal kabul satırı
            <select name="goods_receipt_line_id" required>
                @foreach($receipt->lines as $line)
                    @php($quality = $qualityByLine->get((int) $line->getKey()))
                    @if($quality !== null && (float) $quality->accepted_quantity > 0)
                        <option value="{{ $line->getKey() }}">{{ $line->product_code }} — {{ $line->product_name }}</option>
                    @endif
                @endforeach
            </select>
        </label>
        <label>Referans
            <input type="text" name="reference" maxlength="64" placeholder="Navlun-2026-08 / INV-123" required>
        </label>
        <label>Toplam maliyet farkı
            <input type="number" name="total_value_delta" step="0.000001" placeholder="250.00 veya -50.00" required>
        </label>
        <label>Not
            <input type="text" name="note" maxlength="1000" placeholder="Navlun, sigorta, gerçek maliyet farkı...">
        </label>
        <div><button class="button-primary" type="submit">Maliyet Farkını İşle</button></div>
    </form>
</section>
@endif
@endcan

@if($costAdjustments->isNotEmpty())
<section class="statement-table-card">
    <h2>Maliyet Düzeltme Geçmişi</h2>
    <table class="data-table">
        <thead><tr><th>Zaman</th><th>Satır</th><th>Referans</th><th>Toplam Fark</th><th>Stokta Kalan Pay</th><th>Tüketilmiş Pay</th><th>Yeni Ort. Maliyet</th><th>Yeni Stok Değeri</th></tr></thead>
        <tbody>
        @foreach($costAdjustments as $adjustment)
            <tr>
                <td>{{ $adjustment->occurred_at?->format('d.m.Y H:i') }}</td>
                <td>{{ $adjustment->line?->product_code }} — {{ $adjustment->line?->product_name }}</td>
                <td>{{ $adjustment->reference }}</td>
                <td>{{ $adjustment->total_value_delta }}</td>
                <td>{{ $adjustment->inventory_value_delta }} / {{ $adjustment->on_hand_quantity_basis }}</td>
                <td>{{ $adjustment->consumed_cost_delta }} / {{ $adjustment->consumed_quantity_basis }}</td>
                <td>{{ $adjustment->average_unit_cost_after }}</td>
                <td>{{ $adjustment->inventory_value_after }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endif

@if($qualityEffects->isNotEmpty())
<section class="statement-table-card">
    <h2>Kalite Sınıflandırma Geçmişi</h2>
    <table class="data-table">
        <thead><tr><th>Zaman</th><th>Satır</th><th>Sonuç</th><th>Miktar</th><th>Not</th></tr></thead>
        <tbody>
        @foreach($qualityEffects as $effect)
            <tr>
                <td>{{ $effect->occurred_at?->format('d.m.Y H:i') }}</td>
                <td>{{ $effect->line?->product_code }} — {{ $effect->line?->product_name }}</td>
                <td>{{ $effect->disposition->label() }}</td>
                <td>{{ $effect->quantity }}</td>
                <td>{{ $effect->note ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endif

@if($receipt->note)<section class="detail-card"><p>{{ $receipt->note }}</p></section>@endif
@endsection
