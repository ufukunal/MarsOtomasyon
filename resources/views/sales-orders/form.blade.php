@extends('layouts.app')

@section('title', $order === null ? 'Yeni Satış Siparişi' : 'Sipariş Düzenle')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Satış / Sipariş</p><h1>{{ $order === null ? 'Yeni Satış Siparişi' : $order->number.' Düzenle' }}</h1><p>Net, KDV ve genel toplam request verisinden alınmaz; M5.1 hesap motorunda yeniden üretilir.</p></div>
</section>

@if ($errors->any())
<section class="detail-card"><strong>Sipariş kaydedilemedi.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
@endif

<form method="post" action="{{ $order === null ? route('sales-orders.store') : route('sales-orders.update', $order->getKey()) }}" class="detail-card" id="sales-order-form">
    @csrf
    @if($order !== null) @method('put') @endif
    <div class="form-grid">
        @if($order === null)<label>Numara Serisi<input name="series_code" value="{{ old('series_code', 'default') }}" required></label>@endif
        <label>Cari<select name="account_id" required><option value="">Seçin</option>@foreach($accounts as $account)<option value="{{ $account->getKey() }}" @selected((string) old('account_id', $order?->account_id) === (string) $account->getKey())>{{ $account->code }} — {{ $account->legal_name }}</option>@endforeach</select></label>
        <label>Sipariş Tarihi<input type="date" name="order_date" value="{{ old('order_date', $order?->order_date?->format('Y-m-d') ?? now()->toDateString()) }}" required></label>
        <label>Para Birimi<select name="currency_code" required>@foreach($currencies as $currency)<option value="{{ $currency->code }}" @selected(old('currency_code', $order?->currency_code ?? 'TRY') === $currency->code)>{{ $currency->code }}</option>@endforeach</select></label>
        <label>Belge İskonto %<input name="document_discount_rate" value="{{ old('document_discount_rate', $order?->document_discount_rate ?? '0') }}" required></label>
    </div>

    @php
        $oldLines = old('lines');
        $formLines = is_array($oldLines) ? $oldLines : ($order?->lines?->map(fn($line) => [
            'product_id' => $line->product_id, 'description' => $line->description, 'quantity' => $line->quantity,
            'unit_price' => $line->unit_price, 'price_basis' => $line->price_basis->value,
            'line_discount_rate' => $line->line_discount_rate, 'tax_zero_reason_id' => $line->tax_zero_reason_id,
        ])->all() ?? [['product_id'=>'','description'=>'','quantity'=>'1','unit_price'=>'0','price_basis'=>'net','line_discount_rate'=>'0','tax_zero_reason_id'=>'']]);
    @endphp
    <section class="statement-table-card">
        <table class="data-table" id="sales-order-lines"><thead><tr><th>Ürün</th><th>Açıklama</th><th>Miktar</th><th>Fiyat</th><th>Fiyat Tipi</th><th>İskonto %</th><th>KDV 0 Nedeni</th></tr></thead><tbody>
        @foreach($formLines as $i => $line)
        <tr>
            <td><select name="lines[{{ $i }}][product_id]" required><option value="">Seçin</option>@foreach($products as $product)<option value="{{ $product->getKey() }}" @selected((string)($line['product_id'] ?? '') === (string)$product->getKey())>{{ $product->code }} — {{ $product->name }} (KDV %{{ $product->tax->rate }})</option>@endforeach</select></td>
            <td><input name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}"></td>
            <td><input name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '1' }}" required></td>
            <td><input name="lines[{{ $i }}][unit_price]" value="{{ $line['unit_price'] ?? '0' }}" required></td>
            <td><select name="lines[{{ $i }}][price_basis]">@foreach($priceBases as $basis)<option value="{{ $basis->value }}" @selected(($line['price_basis'] ?? 'net') === $basis->value)>{{ $basis->value === 'net' ? 'KDV Hariç' : 'KDV Dahil' }}</option>@endforeach</select></td>
            <td><input name="lines[{{ $i }}][line_discount_rate]" value="{{ $line['line_discount_rate'] ?? '0' }}" required></td>
            <td><select name="lines[{{ $i }}][tax_zero_reason_id]"><option value="">—</option>@foreach($zeroReasons as $reason)<option value="{{ $reason->getKey() }}" @selected((string)($line['tax_zero_reason_id'] ?? '') === (string)$reason->getKey())>{{ $reason->code }} — {{ $reason->name }}</option>@endforeach</select></td>
        </tr>
        @endforeach
        </tbody></table>
    </section>
    <label>Not<textarea name="note" rows="4">{{ old('note', $order?->note) }}</textarea></label>
    <div class="page-actions"><a href="{{ $order === null ? route('sales-orders.index') : route('sales-orders.show', $order->getKey()) }}">Vazgeç</a><button class="button-primary" type="submit">Kaydet</button></div>
</form>
@endsection
