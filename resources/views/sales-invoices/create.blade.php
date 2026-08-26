@extends('layouts.app')

@section('title', 'Yeni Satış Faturası')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Faturalar</p>
        <h1>Yeni Satış Faturası</h1>
        <p>M8.1 yalnız taslak ve source lineage oluşturur. Fiyat/KDV, posting, cari ve stok etkileri henüz bu ekranda çalışmaz.</p>
    </div>
    <div class="page-actions"><a href="{{ route('sales-invoices.index') }}">Liste</a></div>
</section>

@if($errors->any())
<section class="detail-card"><strong>Fatura oluşturulamadı.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
@endif

<section class="detail-card">
    <h2>Kaynak ve Hukuki Müşteri</h2>
    <p><small><strong>Doğrudan:</strong> Cari seçin. <strong>Sipariş Bağlı:</strong> yalnız sipariş seçin. <strong>İrsaliye Bağlı:</strong> yalnız kesinleşmiş irsaliye seçin. Her modda cariye ait fatura adresi zorunludur.</small></p>
    <form method="POST" action="{{ route('sales-invoices.store') }}">
        @csrf
        <div class="form-grid">
            <label>Fatura Modu
                <select name="mode" required>
                    @foreach($modes as $modeOption)
                        <option value="{{ $modeOption->value }}" @selected(old('mode', $mode->value) === $modeOption->value)>{{ $modeOption->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label>Numara Serisi
                <input name="series_code" value="{{ old('series_code', 'default') }}" maxlength="64">
            </label>
            <label>Fatura Tarihi
                <input type="date" name="invoice_date" value="{{ old('invoice_date', now()->format('Y-m-d')) }}" required>
            </label>
            <label>Doğrudan Cari
                <select name="account_id">
                    <option value="">—</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->getKey() }}" @selected((string) old('account_id') === (string) $account->getKey())>{{ $account->code }} · {{ $account->legal_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Kaynak Sipariş
                <select name="sales_order_id">
                    <option value="">—</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->getKey() }}" @selected((string) old('sales_order_id') === (string) $order->getKey())>{{ $order->number }} · {{ $order->account?->legal_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Kaynak İrsaliye
                <select name="dispatch_id">
                    <option value="">—</option>
                    @foreach($dispatches as $dispatch)
                        <option value="{{ $dispatch->getKey() }}" @selected((string) old('dispatch_id') === (string) $dispatch->getKey())>{{ $dispatch->number }} · {{ $dispatch->account?->legal_name }} · {{ $dispatch->salesOrder?->number }}</option>
                    @endforeach
                </select>
            </label>
            <label>Fatura Adresi
                <select name="source_billing_address_id" required>
                    <option value="">Seçin</option>
                    @foreach($billingAddresses as $address)
                        <option value="{{ $address->getKey() }}" @selected((string) old('source_billing_address_id') === (string) $address->getKey())>Cari #{{ $address->account_id }} · {{ $address->label }} · {{ $address->city }}</option>
                    @endforeach
                </select>
            </label>
            <label>Not
                <textarea name="note" maxlength="5000">{{ old('note') }}</textarea>
            </label>
        </div>

        <h2>Satırlar</h2>
        <p><small>Modunuza göre yalnız ilgili source alanını doldurun. Doğrudan: Ürün. Sipariş bağlı: Sipariş Satırı. İrsaliye bağlı: İrsaliye Satırı. Depo/Konum direct ve allocation'sız sipariş satırında zorunludur; irsaliye bağlı modda kaynak irsaliyeden miras alınır.</small></p>
        <section class="statement-table-card">
        <table class="data-table">
            <thead><tr><th>#</th><th>Ürün (Direct)</th><th>Sipariş Satırı</th><th>İrsaliye Satırı</th><th>Miktar</th><th>Depo / Konum</th></tr></thead>
            <tbody>
            @for($i = 0; $i < 5; $i++)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <select name="lines[{{ $i }}][product_id]">
                            <option value="">—</option>
                            @foreach($products as $product)
                                <option value="{{ $product->getKey() }}" @selected((string) old("lines.$i.product_id") === (string) $product->getKey())>{{ $product->code }} · {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select name="lines[{{ $i }}][sales_order_line_id]">
                            <option value="">—</option>
                            @foreach($orders as $order)
                                @foreach($order->lines as $line)
                                    <option value="{{ $line->getKey() }}" @selected((string) old("lines.$i.sales_order_line_id") === (string) $line->getKey())>{{ $order->number }} / #{{ $line->position }} · {{ $line->product_code }} · {{ $line->quantity }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select name="lines[{{ $i }}][dispatch_line_id]">
                            <option value="">—</option>
                            @foreach($dispatches as $dispatch)
                                @foreach($dispatch->lines as $line)
                                    <option value="{{ $line->getKey() }}" @selected((string) old("lines.$i.dispatch_line_id") === (string) $line->getKey())>{{ $dispatch->number }} / #{{ $line->position }} · {{ $line->product_code }} · {{ $line->quantity }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </td>
                    <td><input name="lines[{{ $i }}][quantity]" value="{{ old("lines.$i.quantity") }}" inputmode="decimal"></td>
                    <td>
                        <select name="lines[{{ $i }}][allocation_key]">
                            <option value="">Kaynak allocation / Seçin</option>
                            @foreach($warehouses as $warehouse)
                                @foreach($warehouse->locations as $location)
                                    @php($key = $warehouse->getKey().':'.$location->getKey())
                                    <option value="{{ $key }}" @selected(old("lines.$i.allocation_key") === $key)>{{ $warehouse->code }} / {{ $location->code }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endfor
            </tbody>
        </table>
        </section>

        <div class="page-actions"><button type="submit">Taslak Faturayı Oluştur</button></div>
    </form>
</section>
@endsection
