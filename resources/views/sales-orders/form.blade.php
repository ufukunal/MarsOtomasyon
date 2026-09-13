@extends('layouts.app')

@section('title', $order === null ? 'Yeni Satış Siparişi' : 'Sipariş Düzenle')

@push('styles')
    @vite('resources/css/sales-order-product-search.css')
@endpush

@push('scripts')
    @vite('resources/js/sales-order-product-search.js')
@endpush

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış Yönetimi / Satış Siparişleri</p>
        <h1>{{ $order === null ? 'Yeni Satış Siparişi' : $order->number.' Düzenle' }}</h1>
        <p>Cari, belge koşulları ve stok allocation bilgilerini girin; toplamlar kayıt sırasında hesap motorunda yeniden üretilir.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('sales-orders.index') }}">Satış Siparişleri</a>
    </div>
</section>

@if ($errors->any())
<section class="notice-error">
    <strong>Sipariş kaydedilemedi.</strong>
    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</section>
@endif

<form method="post" action="{{ $order === null ? route('sales-orders.store') : route('sales-orders.update', $order->getKey()) }}" class="v163-doc v163-doc-form" id="sales-order-form" data-product-search-url="{{ route('sales-orders.product-search') }}">
    @csrf
    @if($order !== null) @method('put') @endif

    <section class="v163-section">
        <div class="v163-section-head">
            Belge Bilgileri
            <small>Müşteri, tarih, para birimi ve iskonto</small>
        </div>
        <div class="v163-section-body">
            <div class="v163-grid">
                @if($order === null)
                    <div class="v163-field">
                        <label class="v163-required" for="sales-order-series">Numara Serisi</label>
                        <input id="sales-order-series" name="series_code" value="{{ old('series_code', 'default') }}" required>
                    </div>
                @endif

                <div class="v163-field full">
                    <label class="v163-required" for="sales-order-account">Cari / Müşteri</label>
                    <select id="sales-order-account" name="account_id" required>
                        <option value="">Cari seçin</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->getKey() }}" @selected((string) old('account_id', $order?->account_id) === (string) $account->getKey())>{{ $account->code }} — {{ $account->legal_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field">
                    <label class="v163-required" for="sales-order-date">Sipariş Tarihi</label>
                    <input id="sales-order-date" type="date" name="order_date" value="{{ old('order_date', $order?->order_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                </div>

                <div class="v163-field">
                    <label class="v163-required" for="sales-order-currency">Para Birimi</label>
                    <select id="sales-order-currency" name="currency_code" required>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->code }}" @selected(old('currency_code', $order?->currency_code ?? 'TRY') === $currency->code)>{{ $currency->code }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field">
                    <label class="v163-required" for="sales-order-discount">Belge İskonto %</label>
                    <input id="sales-order-discount" name="document_discount_rate" inputmode="decimal" value="{{ old('document_discount_rate', $order?->document_discount_rate ?? '0') }}" required>
                </div>
            </div>
        </div>
    </section>

    @php
        $oldLines = old('lines');
        $formLines = is_array($oldLines) ? $oldLines : ($order?->lines?->map(fn($line) => [
            'logical_line_key' => $line->logical_line_key,
            'product_id' => $line->product_id, 'warehouse_id' => $line->warehouse_id, 'location_id' => $line->location_id,
            'description' => $line->description, 'quantity' => $line->quantity,
            'unit_price' => $line->unit_price, 'price_basis' => $line->price_basis->value,
            'line_discount_rate' => $line->line_discount_rate, 'tax_is_zeroed' => $line->tax_is_zeroed,
            'tax_zero_reason_id' => $line->tax_zero_reason_id,
        ])->all() ?? [['logical_line_key'=>'','product_id'=>'','warehouse_id'=>'','location_id'=>'','description'=>'','quantity'=>'1','unit_price'=>'0','price_basis'=>'net','line_discount_rate'=>'0','tax_is_zeroed'=>false,'tax_zero_reason_id'=>'']]);
    @endphp

    <section class="v163-section">
        <div class="v163-section-head">
            Sipariş Kalemleri
            <small>Ürün, stok allocation, miktar ve fiyat</small>
        </div>
        <div class="v163-table-wrap">
            <table class="v163-table" id="sales-order-lines">
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th>Depo</th>
                        <th>Lokasyon</th>
                        <th>Açıklama</th>
                        <th>Miktar</th>
                        <th>Birim Fiyat</th>
                        <th>Fiyat Tipi</th>
                        <th>İskonto %</th>
                        <th>KDV Sıfırla</th>
                        <th>KDV 0 Nedeni</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($formLines as $i => $line)
                    @php
                        $productId = isset($line['product_id']) && is_numeric($line['product_id']) ? (int) $line['product_id'] : null;
                        $productLabel = $productId === null ? '' : ($selectedProductLabels[$productId] ?? '');
                    @endphp
                    <tr>
                        <td>
                            <div class="product-search-entry" data-product-search-entry>
                                <input type="hidden" name="lines[{{ $i }}][logical_line_key]" value="{{ $line['logical_line_key'] ?? '' }}">
                                <input type="hidden" name="lines[{{ $i }}][product_id]" value="{{ $productId ?? '' }}" data-product-id>
                                <input type="search" value="{{ $productLabel }}" placeholder="SKU, barkod, QR veya ürün adı" autocomplete="off" data-product-search-input aria-label="Ürün ara" aria-autocomplete="list" aria-expanded="false" required>
                                <div class="product-search-results" data-product-search-results role="listbox" hidden></div>
                                <small data-product-search-help>SKU, barkod, QR veya ürün adı</small>
                            </div>
                        </td>
                        <td>
                            <select class="wide" name="lines[{{ $i }}][warehouse_id]" aria-label="Depo">
                                <option value="">Depo seçin</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->getKey() }}" @selected((string)($line['warehouse_id'] ?? '') === (string)$warehouse->getKey())>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="wide" name="lines[{{ $i }}][location_id]" aria-label="Lokasyon">
                                <option value="">Lokasyon seçin</option>
                                @foreach($warehouses as $warehouse)
                                    @foreach($warehouse->locations as $location)
                                        <option value="{{ $location->getKey() }}" @selected((string)($line['location_id'] ?? '') === (string)$location->getKey())>{{ $warehouse->code }} / {{ $location->code }} — {{ $location->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </td>
                        <td><input class="wide" name="lines[{{ $i }}][description]" aria-label="Kalem açıklaması" value="{{ $line['description'] ?? '' }}" placeholder="Kalem açıklaması"></td>
                        <td><input class="v163-qty" name="lines[{{ $i }}][quantity]" aria-label="Miktar" inputmode="decimal" value="{{ $line['quantity'] ?? '1' }}" required></td>
                        <td><input class="v163-money" name="lines[{{ $i }}][unit_price]" aria-label="Birim fiyat" inputmode="decimal" value="{{ $line['unit_price'] ?? '0' }}" data-product-unit-price required></td>
                        <td>
                            <select class="v163-compact" name="lines[{{ $i }}][price_basis]" aria-label="Fiyat tipi">
                                @foreach($priceBases as $basis)
                                    <option value="{{ $basis->value }}" @selected(($line['price_basis'] ?? 'net') === $basis->value)>{{ $basis->value === 'net' ? 'KDV Hariç' : 'KDV Dahil' }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="v163-compact" name="lines[{{ $i }}][line_discount_rate]" aria-label="Kalem iskonto yüzdesi" inputmode="decimal" value="{{ $line['line_discount_rate'] ?? '0' }}" required></td>
                        <td>
                            <label style="display:flex;align-items:center;gap:5px;white-space:nowrap">
                                <input type="checkbox" name="lines[{{ $i }}][tax_is_zeroed]" value="1" @checked((bool)($line['tax_is_zeroed'] ?? false)) style="width:auto;min-width:16px;height:16px">
                                Sıfırla
                            </label>
                        </td>
                        <td>
                            <select class="wide" name="lines[{{ $i }}][tax_zero_reason_id]" aria-label="KDV sıfır nedeni">
                                <option value="">—</option>
                                @foreach($zeroReasons as $reason)
                                    <option value="{{ $reason->getKey() }}" @selected((string)($line['tax_zero_reason_id'] ?? '') === (string)$reason->getKey())>{{ $reason->code }} — {{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="v163-info">Depo ve lokasyon seçimi stok rezervasyonu için kullanılır. KDV sıfırlama yalnız geçerli neden ile kaydedilir.</div>
    </section>

    <section class="v163-section">
        <div class="v163-section-head">Notlar</div>
        <div class="v163-section-body v163-note">
            <textarea id="sales-order-note" name="note" rows="4" aria-label="Sipariş notu" placeholder="Sipariş notu, teslimat veya müşteri talebi">{{ old('note', $order?->note) }}</textarea>
        </div>
    </section>

    <div class="v163-footer">
        <span class="v163-footer-copy">* Zorunlu alanlar · Fiyat ve vergi toplamları sunucuda yeniden hesaplanır.</span>
        <span class="v163-grow"></span>
        <a href="{{ $order === null ? route('sales-orders.index') : route('sales-orders.show', $order->getKey()) }}">Vazgeç</a>
        <button class="button-primary" type="submit">Kaydet</button>
    </div>
</form>
@endsection
