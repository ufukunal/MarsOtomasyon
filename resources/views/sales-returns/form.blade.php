@extends('layouts.app')

@section('title', 'Yeni RMA')

@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">Satış / İadeler</p><h1>Yeni İade / RMA</h1><p>Kaynak yalnız kesinleşmiş satış faturasıdır. Yetkilendirme aşamasında kalan iade kapasitesi atomik olarak rezerve edilir.</p></div></section>
<section class="detail-card">
<form method="GET" action="{{ route('returns.create') }}" class="form-grid">
<div><label for="sales_invoice_id">Satış Faturası</label><select id="sales_invoice_id" name="sales_invoice_id"><option value="">Seçiniz</option>@foreach($invoices as $invoice)<option value="{{ $invoice->getKey() }}" @selected($selectedInvoice?->getKey() === $invoice->getKey())>{{ $invoice->number }} — {{ $invoice->account?->legal_name }} — {{ $invoice->invoice_date?->format('d.m.Y') }}</option>@endforeach</select></div>
<div class="page-actions"><button type="submit">Fatura Satırlarını Getir</button></div>
</form>
</section>
@if($selectedInvoice)
<form method="POST" action="{{ route('returns.store') }}">@csrf
<input type="hidden" name="sales_invoice_id" value="{{ $selectedInvoice->getKey() }}">
<section class="detail-card"><div class="form-grid"><div><label for="series_code">RMA Seri</label><input id="series_code" name="series_code" value="{{ old('series_code', 'default') }}"></div><div><label for="return_date">İade Tarihi</label><input id="return_date" type="date" name="return_date" value="{{ old('return_date', now()->format('Y-m-d')) }}" min="{{ $selectedInvoice->invoice_date?->format('Y-m-d') }}" required></div><div><label for="note">Not</label><input id="note" name="note" value="{{ old('note') }}"></div></div></section>
<section class="statement-table-card"><table class="data-table"><thead><tr><th>Fatura Satırı</th><th>Ürün</th><th>Faturalanan</th><th>Kalan İade Kapasitesi</th><th>İade Miktarı</th><th>Neden Kodu</th></tr></thead><tbody>
@foreach($selectedInvoice->lines as $row => $line)
<tr>
<td>#{{ $line->getKey() }}<input type="hidden" name="lines[{{ $row }}][sales_invoice_line_id]" value="{{ $line->getKey() }}"></td>
<td>{{ $line->product_code }} — {{ $line->product_name }}</td>
<td>{{ $line->quantity }}</td>
<td>{{ $remainingByLine->get($line->getKey(), '0.000000') }}</td>
<td><input name="lines[{{ $row }}][quantity]" value="{{ old('lines.'.$row.'.quantity', '0') }}" inputmode="decimal"></td>
<td><input name="lines[{{ $row }}][reason_code]" value="{{ old('lines.'.$row.'.reason_code', 'customer_return') }}" maxlength="64" required></td>
</tr>
@endforeach
</tbody></table></section>
<div class="page-actions"><button class="button-primary" type="submit">RMA Taslağı Oluştur</button></div>
</form>
@endif
@endsection
