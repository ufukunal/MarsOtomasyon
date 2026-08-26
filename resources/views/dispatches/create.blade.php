@extends('layouts.app')

@section('title', 'Yeni İrsaliye')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Satış / İrsaliye</p><h1>Yeni İrsaliye</h1><p>M7.2 sevk miktarını sipariş progress ve mevcut taslak taahhütleriyle sınırlar. Taslak belge stok hareketi veya dispatched progress üretmez.</p></div>
    <div class="page-actions"><a href="{{ route('dispatches.index') }}">Liste</a></div>
</section>

<section class="detail-card">
    <form method="get" action="{{ route('dispatches.create') }}">
        <div class="form-grid">
            <label>Kaynak Sipariş
                <select name="sales_order_id" required>
                    <option value="">Sipariş seçin</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->getKey() }}" @selected($selectedOrder?->getKey() === $order->getKey())>{{ $order->number }} — {{ $order->account?->legal_name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="page-actions"><button class="button-secondary" type="submit">Siparişi Yükle</button></div>
    </form>
</section>

@if($selectedOrder)
<form method="post" action="{{ route('dispatches.store') }}">
    @csrf
    <input type="hidden" name="sales_order_id" value="{{ $selectedOrder->getKey() }}">

    <section class="detail-card">
        <div class="form-grid">
            <div><small>Sipariş</small><strong>{{ $selectedOrder->number }}</strong></div>
            <div><small>Cari</small><strong>{{ $selectedOrder->account?->legal_name }}</strong></div>
            <label>Belge Serisi<input name="series_code" value="{{ old('series_code', 'default') }}" maxlength="64"></label>
            <label>İrsaliye Tarihi<input type="date" name="dispatch_date" value="{{ old('dispatch_date', now()->toDateString()) }}" required></label>
            <label>Sevk Adresi
                <select name="source_address_id" required>
                    <option value="">Adres seçin</option>
                    @foreach($addresses as $address)
                        <option value="{{ $address->getKey() }}" @selected((string) old('source_address_id', $address->is_default ? $address->getKey() : '') === (string) $address->getKey())>
                            {{ $address->label }} — {{ $address->line1 }}, {{ $address->city }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>Taşıyıcı<input name="carrier_name" value="{{ old('carrier_name') }}" maxlength="200" placeholder="Kargo / nakliye firması"></label>
            <label>Servis<input name="carrier_service" value="{{ old('carrier_service') }}" maxlength="120" placeholder="Standart, ekspres, ambar..."></label>
            <label>Takip No<input name="tracking_number" value="{{ old('tracking_number') }}" maxlength="120"></label>
        </div>
        @if($addresses->isEmpty())<p>Bu cariye ait sevk adresi bulunmuyor. İrsaliye oluşturmak için önce cari kartına sevk adresi ekleyin.</p>@endif
    </section>

    <section class="statement-table-card">
        <table class="data-table"><thead><tr><th>#</th><th>Ürün</th><th>Depo / Konum</th><th>Sipariş</th><th>Önceki</th><th>Bu İrsaliye</th><th>Kalan Kapasite</th></tr></thead><tbody>
        @php($shippableLines = 0)
        @foreach($selectedOrder->lines as $index => $line)
            @php($capacity = $capacities->get($line->getKey()))
            @php($remaining = (string) ($capacity?->remaining_quantity ?? '0.000000'))
            @php($hasCapacity = ! str_starts_with($remaining, '-') && $remaining !== '0.000000')
            @if($hasCapacity) @php($shippableLines++) @endif
            <tr>
                <td>
                    {{ $line->position }}
                    @if($hasCapacity)<input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $line->getKey() }}">@endif
                </td>
                <td>{{ $line->product_code }} — {{ $line->product_name }}@if($line->description)<br><small>{{ $line->description }}</small>@endif</td>
                <td>
                    @if($hasCapacity)
                        @if($line->warehouse_id !== null && $line->location_id !== null)
                            {{ $line->warehouse?->code ?? '—' }} / {{ $line->location?->code ?? '—' }}<br><small>Sipariş allocation</small>
                        @else
                            <select name="lines[{{ $index }}][allocation_key]" required>
                                <option value="">Sevk depo / konumu seçin</option>
                                @foreach($warehouses as $warehouse)
                                    @foreach($warehouse->locations as $location)
                                        @php($allocationKey = $warehouse->getKey().':'.$location->getKey())
                                        <option value="{{ $allocationKey }}" @selected((string) old('lines.'.$index.'.allocation_key') === (string) $allocationKey)>{{ $warehouse->code }} — {{ $location->code }} / {{ $location->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        @endif
                    @else
                        <small>Sevk kapasitesi kalmadı.</small>
                    @endif
                </td>
                <td>{{ $capacity?->ordered_quantity ?? $line->quantity }}</td>
                <td>{{ $capacity?->previous_quantity ?? '0.000000' }}</td>
                <td>
                    @if($hasCapacity)
                        <input name="lines[{{ $index }}][quantity]" value="{{ old('lines.'.$index.'.quantity', $remaining) }}" inputmode="decimal" required>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $remaining }}</td>
            </tr>
        @endforeach
        </tbody></table>
        <p><small>Önceki = net sevk progress + diğer aktif taslak irsaliye taahhütleri. Kalan kapasite iptal edilmiş miktarı da dikkate alır.</small></p>
    </section>

    <section class="detail-card">
        <label>Not<textarea name="note" rows="4" maxlength="5000">{{ old('note') }}</textarea></label>
        @if($errors->any())<div><strong>Form doğrulanamadı.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="page-actions"><button class="button-primary" type="submit" @disabled($addresses->isEmpty() || $shippableLines === 0)>Taslak İrsaliye Oluştur</button></div>
    </section>
</form>
@endif
@endsection
