@extends('layouts.app')

@section('title', 'Yeni İrsaliye')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış Yönetimi / Sevkiyat / İrsaliye</p>
        <h1>Yeni İrsaliye</h1>
        <p>Önce kaynak satış siparişini seçin; ardından sevk bilgileri ve gönderilecek kalemleri düzenleyin.</p>
    </div>
    <div class="page-actions"><a href="{{ route('dispatches.index') }}">İrsaliyeler</a></div>
</section>

<section class="v163-doc v163-doc-form">
    <section class="v163-section">
        <div class="v163-section-head">
            Kaynak Sipariş
            <small>İrsaliyenin bağlı olduğu satış siparişi</small>
        </div>
        <div class="v163-section-body">
            <form method="get" action="{{ route('dispatches.create') }}">
                <div class="v163-grid">
                    <div class="v163-field full">
                        <label class="v163-required" for="dispatch-sales-order">Satış Siparişi</label>
                        <select id="dispatch-sales-order" name="sales_order_id" required>
                            <option value="">Sipariş seçin</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->getKey() }}" @selected($selectedOrder?->getKey() === $order->getKey())>{{ $order->number }} — {{ $order->account?->legal_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="v163-footer" style="position:static;margin-top:10px">
                    <span class="v163-footer-copy">Sipariş seçildiğinde sevk edilebilir kalemler ve kalan miktarlar yüklenir.</span>
                    <span class="v163-grow"></span>
                    <button class="button-secondary" type="submit">Siparişi Yükle</button>
                </div>
            </form>
        </div>
    </section>
</section>

@if($selectedOrder)
<form method="post" action="{{ route('dispatches.store') }}" class="v163-doc v163-doc-form">
    @csrf
    <input type="hidden" name="sales_order_id" value="{{ $selectedOrder->getKey() }}">

    <section class="v163-section">
        <div class="v163-section-head">
            Sevk Bilgileri
            <small>Belge, adres ve taşıyıcı bilgileri</small>
        </div>
        <div class="v163-section-body">
            <div class="v163-context-grid" style="margin-bottom:11px">
                <div><small>Sipariş</small><strong>{{ $selectedOrder->number }}</strong></div>
                <div><small>Cari</small><strong>{{ $selectedOrder->account?->legal_name }}</strong></div>
                <div><small>Sipariş Tarihi</small><strong>{{ $selectedOrder->order_date?->format('d.m.Y') ?? '—' }}</strong></div>
                <div><small>Para Birimi</small><strong>{{ $selectedOrder->currency_code ?? '—' }}</strong></div>
            </div>

            <div class="v163-grid">
                <div class="v163-field">
                    <label for="dispatch-series">Belge Serisi</label>
                    <input id="dispatch-series" name="series_code" value="{{ old('series_code', 'default') }}" maxlength="64">
                </div>

                <div class="v163-field">
                    <label class="v163-required" for="dispatch-date">İrsaliye Tarihi</label>
                    <input id="dispatch-date" type="date" name="dispatch_date" value="{{ old('dispatch_date', now()->toDateString()) }}" required>
                </div>

                <div class="v163-field full">
                    <label class="v163-required" for="dispatch-address">Sevk Adresi</label>
                    <select id="dispatch-address" name="source_address_id" required>
                        <option value="">Adres seçin</option>
                        @foreach($addresses as $address)
                            <option value="{{ $address->getKey() }}" @selected((string) old('source_address_id', $address->is_default ? $address->getKey() : '') === (string) $address->getKey())>
                                {{ $address->label }} — {{ $address->line1 }}, {{ $address->city }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field">
                    <label for="dispatch-carrier">Taşıyıcı</label>
                    <input id="dispatch-carrier" name="carrier_name" value="{{ old('carrier_name') }}" maxlength="200" placeholder="Kargo / nakliye firması">
                </div>

                <div class="v163-field">
                    <label for="dispatch-service">Servis</label>
                    <input id="dispatch-service" name="carrier_service" value="{{ old('carrier_service') }}" maxlength="120" placeholder="Standart, ekspres, ambar...">
                </div>

                <div class="v163-field">
                    <label for="dispatch-tracking">Takip No</label>
                    <input id="dispatch-tracking" name="tracking_number" value="{{ old('tracking_number') }}" maxlength="120" placeholder="Kargo takip / sevk referansı">
                </div>
            </div>

            @if($addresses->isEmpty())
                <div class="v163-warn" style="margin-top:10px">Bu cariye ait sevk adresi bulunmuyor. İrsaliye oluşturmak için önce cari kartına sevk adresi ekleyin.</div>
            @endif
        </div>
    </section>

    <section class="v163-section">
        <div class="v163-section-head">
            Sevk Kalemleri
            <small>Sipariş miktarı, önceki sevk ve bu irsaliye miktarı</small>
        </div>
        <div class="v163-table-wrap">
            <table class="v163-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ürün</th>
                        <th>Depo / Konum</th>
                        <th>Sipariş</th>
                        <th>Önceki</th>
                        <th>Bu İrsaliye</th>
                        <th>Kalan Kapasite</th>
                    </tr>
                </thead>
                <tbody>
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
                        <td>
                            <strong>{{ $line->product_code }} — {{ $line->product_name }}</strong>
                            @if($line->description)<br><small>{{ $line->description }}</small>@endif
                        </td>
                        <td>
                            @if($hasCapacity)
                                @if($line->warehouse_id !== null && $line->location_id !== null)
                                    {{ $line->warehouse?->code ?? '—' }} / {{ $line->location?->code ?? '—' }}<br><small>Sipariş allocation</small>
                                @else
                                    <select class="wide" name="lines[{{ $index }}][allocation_key]" aria-label="Sevk depo ve lokasyonu" required>
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
                                <input class="v163-qty" name="lines[{{ $index }}][quantity]" aria-label="Bu irsaliye miktarı" value="{{ old('lines.'.$index.'.quantity', $remaining) }}" inputmode="decimal" required>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $remaining }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="v163-info">Önceki miktar; net sevk progress'i ve diğer aktif taslak irsaliye taahhütlerini içerir. Kalan kapasite iptal edilmiş miktarı dikkate alır.</div>
    </section>

    <section class="v163-section">
        <div class="v163-section-head">Notlar</div>
        <div class="v163-section-body v163-note">
            <textarea id="dispatch-note" name="note" rows="4" maxlength="5000" aria-label="İrsaliye notu" placeholder="Sevk, paketleme veya taşıyıcı notu">{{ old('note') }}</textarea>
            @if($errors->any())
                <div class="v163-warn" style="margin-top:8px">
                    <strong>Form doğrulanamadı.</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
        </div>
    </section>

    <div class="v163-footer">
        <span class="v163-footer-copy">Taslak irsaliye stok hareketi üretmez; sevk işlemi belge lifecycle'ında ayrıca tamamlanır.</span>
        <span class="v163-grow"></span>
        <a href="{{ route('dispatches.index') }}">Vazgeç</a>
        <button class="button-primary" type="submit" @disabled($addresses->isEmpty() || $shippableLines === 0)>Taslak İrsaliye Oluştur</button>
    </div>
</form>
@endif
@endsection
