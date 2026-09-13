@extends('layouts.app')

@section('title', 'Yeni Satış Faturası')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış Yönetimi / Satış Faturaları</p>
        <h1>Yeni Satış Faturası</h1>
        <p>Doğrudan, sipariş veya irsaliye kaynaklı faturayı seçin; hukuki müşteri, ticari koşullar ve faturalama kapasitesi sunucu tarafında korunur.</p>
    </div>
    <div class="page-actions"><a href="{{ route('sales-invoices.index') }}">Satış Faturaları</a></div>
</section>

@if($errors->any())
<section class="notice-error">
    <strong>Fatura oluşturulamadı.</strong>
    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</section>
@endif

<form method="POST" action="{{ route('sales-invoices.store') }}" class="v163-doc v163-doc-form">
    @csrf

    <section class="v163-section">
        <div class="v163-section-head">
            Belge Bilgileri
            <small>Fatura modu, kaynak belge ve müşteri bilgileri</small>
        </div>
        <div class="v163-section-body">
            <div class="v163-info" style="margin-bottom:10px">
                <strong>Doğrudan:</strong> cari, fiyat/vergi ve belge indirimi girilir. <strong>Sipariş/İrsaliye bağlı:</strong> ticari koşullar kaynak sipariş snapshotından miras alınır.
            </div>

            <div class="v163-grid">
                <div class="v163-field">
                    <label class="v163-required" for="invoice-mode">Fatura Modu</label>
                    <select id="invoice-mode" name="mode" required>
                        @foreach($modes as $modeOption)
                            <option value="{{ $modeOption->value }}" @selected(old('mode', $mode->value) === $modeOption->value)>{{ $modeOption->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field">
                    <label for="invoice-series">Numara Serisi</label>
                    <input id="invoice-series" name="series_code" value="{{ old('series_code', 'default') }}" maxlength="64">
                </div>

                <div class="v163-field">
                    <label class="v163-required" for="invoice-date">Fatura Tarihi</label>
                    <input id="invoice-date" type="date" name="invoice_date" value="{{ old('invoice_date', now()->format('Y-m-d')) }}" required>
                </div>

                <div class="v163-field">
                    <label for="invoice-discount">Belge İndirimi %</label>
                    <input id="invoice-discount" name="document_discount_rate" value="{{ old('document_discount_rate') }}" inputmode="decimal" placeholder="Yalnız doğrudan fatura">
                </div>

                <div class="v163-field full">
                    <label for="invoice-account">Doğrudan Cari</label>
                    <select id="invoice-account" name="account_id">
                        <option value="">Cari seçilmedi</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->getKey() }}" @selected((string) old('account_id') === (string) $account->getKey())>{{ $account->code }} · {{ $account->legal_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field full">
                    <label for="invoice-order">Kaynak Sipariş</label>
                    <select id="invoice-order" name="sales_order_id">
                        <option value="">Sipariş seçilmedi</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->getKey() }}" @selected((string) old('sales_order_id') === (string) $order->getKey())>{{ $order->number }} · {{ $order->account?->legal_name }} · İndirim %{{ $order->document_discount_rate }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field full">
                    <label for="invoice-dispatch">Kaynak İrsaliye</label>
                    <select id="invoice-dispatch" name="dispatch_id">
                        <option value="">İrsaliye seçilmedi</option>
                        @foreach($dispatches as $dispatch)
                            <option value="{{ $dispatch->getKey() }}" @selected((string) old('dispatch_id') === (string) $dispatch->getKey())>{{ $dispatch->number }} · {{ $dispatch->account?->legal_name }} · {{ $dispatch->salesOrder?->number }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field full">
                    <label class="v163-required" for="invoice-address">Fatura Adresi</label>
                    <select id="invoice-address" name="source_billing_address_id" required>
                        <option value="">Fatura adresi seçin</option>
                        @foreach($billingAddresses as $address)
                            <option value="{{ $address->getKey() }}" @selected((string) old('source_billing_address_id') === (string) $address->getKey())>Cari #{{ $address->account_id }} · {{ $address->label }} · {{ $address->city }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section class="v163-section">
        <div class="v163-section-head">
            Fatura Kalemleri
            <small>Kaynak, miktar, allocation, fiyat ve vergi koşulları</small>
        </div>
        <div class="v163-table-wrap">
            <table class="v163-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kaynak / Ürün</th>
                        <th>Miktar / Allocation</th>
                        <th>Fiyat / İskonto</th>
                        <th>Vergi</th>
                    </tr>
                </thead>
                <tbody>
                @for($i = 0; $i < 5; $i++)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <select class="wide" name="lines[{{ $i }}][product_id]" aria-label="Fatura kalemi ürün">
                                <option value="">Doğrudan ürün seçin</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->getKey() }}" @selected((string) old("lines.$i.product_id") === (string) $product->getKey())>{{ $product->code }} · {{ $product->name }} · KDV %{{ $product->tax?->rate ?? '?' }}</option>
                                @endforeach
                            </select>
                            <select class="wide" name="lines[{{ $i }}][sales_order_line_id]" aria-label="Kaynak sipariş satırı" style="margin-top:5px">
                                <option value="">Sipariş satırı seçilmedi</option>
                                @foreach($orders as $order)
                                    @foreach($order->lines as $line)
                                        @php($capacity = $orderInvoiceCapacities->get($line->getKey()))
                                        <option value="{{ $line->getKey() }}" @selected((string) old("lines.$i.sales_order_line_id") === (string) $line->getKey())>{{ $order->number }} / #{{ $line->position }} · {{ $line->product_code }} · Sipariş {{ $line->quantity }} · Önceki {{ $capacity?->previous_quantity ?? '0.000000' }} · Kalan {{ $capacity?->remaining_quantity ?? $line->quantity }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <select class="wide" name="lines[{{ $i }}][dispatch_line_id]" aria-label="Kaynak irsaliye satırı" style="margin-top:5px">
                                <option value="">İrsaliye satırı seçilmedi</option>
                                @foreach($dispatches as $dispatch)
                                    @foreach($dispatch->lines as $line)
                                        @php($capacity = $orderInvoiceCapacities->get($line->sales_order_line_id))
                                        <option value="{{ $line->getKey() }}" @selected((string) old("lines.$i.dispatch_line_id") === (string) $line->getKey())>{{ $dispatch->number }} / #{{ $line->position }} · {{ $line->product_code }} · İrsaliye {{ $line->quantity }} · Önceki {{ $capacity?->previous_quantity ?? '0.000000' }} · Sipariş Kalan {{ $capacity?->remaining_quantity ?? '?' }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input class="v163-qty" name="lines[{{ $i }}][quantity]" aria-label="Fatura miktarı" value="{{ old("lines.$i.quantity") }}" inputmode="decimal" placeholder="Miktar">
                            <select class="wide" name="lines[{{ $i }}][allocation_key]" aria-label="Kaynak allocation" style="margin-top:5px">
                                <option value="">Allocation seçin</option>
                                @foreach($warehouses as $warehouse)
                                    @foreach($warehouse->locations as $location)
                                        @php($key = $warehouse->getKey().':'.$location->getKey())
                                        <option value="{{ $key }}" @selected(old("lines.$i.allocation_key") === $key)>{{ $warehouse->code }} / {{ $location->code }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="wide" name="lines[{{ $i }}][price_basis]" aria-label="Fiyat tipi">
                                <option value="">Kaynak fiyat / seçin</option>
                                @foreach($priceBases as $basis)
                                    <option value="{{ $basis->value }}" @selected(old("lines.$i.price_basis") === $basis->value)>{{ strtoupper($basis->value) }}</option>
                                @endforeach
                            </select>
                            <input class="v163-money" name="lines[{{ $i }}][unit_price]" aria-label="Birim fiyat" value="{{ old("lines.$i.unit_price") }}" inputmode="decimal" placeholder="Birim fiyat" style="margin-top:5px">
                            <input class="v163-compact" name="lines[{{ $i }}][line_discount_rate]" aria-label="Satır indirimi" value="{{ old("lines.$i.line_discount_rate") }}" inputmode="decimal" placeholder="İskonto %" style="margin-top:5px">
                        </td>
                        <td>
                            <label style="display:flex;align-items:center;gap:5px;white-space:nowrap">
                                <input type="checkbox" name="lines[{{ $i }}][tax_is_zeroed]" value="1" @checked(old("lines.$i.tax_is_zeroed")) style="width:auto;min-width:16px;height:16px">
                                KDV'yi sıfırla
                            </label>
                            <select class="wide" name="lines[{{ $i }}][tax_zero_reason_id]" aria-label="KDV sıfır nedeni" style="margin-top:5px">
                                <option value="">KDV sıfır nedeni</option>
                                @foreach($zeroReasons as $reason)
                                    <option value="{{ $reason->getKey() }}" @selected((string) old("lines.$i.tax_zero_reason_id") === (string) $reason->getKey())>{{ $reason->code }} · {{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
        <div class="v163-info">Linked satırlarda fiyat, vergi ve belge indirimi kaynak siparişten gelir. Sipariş kaynaklı satırlarda Önceki/Kalan değerleri kesinleşmiş fatura progress'i ve aktif taslak commitment'ları birlikte içerir.</div>
    </section>

    <section class="v163-section">
        <div class="v163-section-head">Notlar</div>
        <div class="v163-section-body v163-note">
            <textarea id="invoice-note" name="note" maxlength="5000" aria-label="Fatura notu" placeholder="Fatura açıklaması veya müşteri notu">{{ old('note') }}</textarea>
        </div>
    </section>

    <div class="v163-footer">
        <span class="v163-footer-copy">Ticari alanlar ve faturalama kapasitesi server-side doğrulanır.</span>
        <span class="v163-grow"></span>
        <a href="{{ route('sales-invoices.index') }}">Vazgeç</a>
        <button class="button-primary" type="submit">Taslak Faturayı Oluştur</button>
    </div>
</form>
@endsection
