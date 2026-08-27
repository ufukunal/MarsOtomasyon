@extends('layouts.app')

@section('title', $receipt ? 'Mal Kabul Düzenle' : 'Yeni Mal Kabul')

@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">Alış / Mal Kabul</p><h1>{{ $receipt ? $receipt->number.' Düzenle' : 'Yeni Mal Kabul' }}</h1><p>Kısmi kabul desteklenir. Sıfır fiziksel teslim girilen sipariş satırları belgeye eklenmez.</p></div><div class="page-actions"><a href="{{ route('goods-receipts.index') }}">Liste</a></div></section>

@if(!$selectedOrder)
<section class="detail-card">
<form method="GET" action="{{ route('goods-receipts.create') }}" class="form-grid">
    <label>Kaynak Satınalma Siparişi<select name="purchase_order_id" required><option value="">Seçin</option>@foreach($orders as $order)<option value="{{ $order->getKey() }}">{{ $order->number }} — {{ $order->account?->legal_name }}</option>@endforeach</select></label>
    <div class="form-actions"><button class="button-primary" type="submit">Siparişi Yükle</button></div>
</form>
</section>
@else
<form method="POST" action="{{ $receipt ? route('goods-receipts.update', $receipt->getKey()) : route('goods-receipts.store') }}">
    @csrf @if($receipt) @method('PUT') @endif
    <input type="hidden" name="purchase_order_id" value="{{ $selectedOrder->getKey() }}">
    <section class="detail-card"><div class="form-grid">
        <div><small>Kaynak Sipariş</small><strong>{{ $selectedOrder->number }}</strong></div>
        <div><small>Tedarikçi</small><strong>{{ $selectedOrder->account?->legal_name }}</strong></div>
        @if(!$receipt)<label>Seri<input name="series_code" value="{{ old('series_code', 'default') }}" maxlength="64"></label>@endif
        <label>Mal Kabul Tarihi<input type="date" name="receipt_date" value="{{ old('receipt_date', $receipt?->receipt_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required></label>
        <label class="form-span-2">Not<textarea name="note" maxlength="5000">{{ old('note', $receipt?->note) }}</textarea></label>
    </div></section>

    @if($errors->any())<div class="notice-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="statement-table-card"><table class="data-table"><thead><tr><th>Ürün</th><th>Kabul Kalan</th><th>Depo</th><th>Lokasyon</th><th>Fiziksel</th><th>Accepted</th><th>Pending</th><th>Rejected</th><th>Not</th></tr></thead><tbody>
    @foreach($selectedOrder->lines as $index => $line)
        @php($existing = $existingLines->get($line->getKey()))
        <tr>
            <td><input type="hidden" name="lines[{{ $index }}][purchase_order_line_id]" value="{{ $line->getKey() }}"><strong>{{ $line->product_code }}</strong><br><small>{{ $line->product_name }}</small></td>
            <td>{{ $line->progress?->receive_remaining_quantity ?? $line->quantity }}</td>
            <td><select name="lines[{{ $index }}][warehouse_id]" required><option value="">Seçin</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->getKey() }}" @selected((string) old("lines.$index.warehouse_id", $existing?->warehouse_id ?? $line->warehouse_id) === (string) $warehouse->getKey())>{{ $warehouse->code }}</option>@endforeach</select></td>
            <td><select name="lines[{{ $index }}][location_id]" required><option value="">Seçin</option>@foreach($warehouses as $warehouse)@foreach($warehouse->locations as $location)<option value="{{ $location->getKey() }}" @selected((string) old("lines.$index.location_id", $existing?->location_id ?? $line->location_id) === (string) $location->getKey())>{{ $warehouse->code }} / {{ $location->code }}</option>@endforeach @endforeach</select></td>
            <td><input name="lines[{{ $index }}][received_quantity]" value="{{ old("lines.$index.received_quantity", $existing?->received_quantity ?? '0') }}" inputmode="decimal" required></td>
            <td><input name="lines[{{ $index }}][accepted_quantity]" value="{{ old("lines.$index.accepted_quantity", $existing?->accepted_quantity ?? '0') }}" inputmode="decimal" required></td>
            <td><input name="lines[{{ $index }}][pending_quantity]" value="{{ old("lines.$index.pending_quantity", $existing?->pending_quantity ?? '0') }}" inputmode="decimal" required></td>
            <td><input name="lines[{{ $index }}][rejected_quantity]" value="{{ old("lines.$index.rejected_quantity", $existing?->rejected_quantity ?? '0') }}" inputmode="decimal" required></td>
            <td><input name="lines[{{ $index }}][note]" value="{{ old("lines.$index.note", $existing?->note) }}" maxlength="1000"></td>
        </tr>
    @endforeach
    </tbody></table></section>
    <div class="form-actions"><button class="button-primary" type="submit">{{ $receipt ? 'Güncelle' : 'Taslak Oluştur' }}</button></div>
</form>
@endif
@endsection
