@extends('layouts.app')

@section('title', 'RMA '.$return->number)

@section('app-content')
<section class="workspace-hero">
<div><p class="eyebrow">Satış / İade / RMA</p><h1>{{ $return->number }}</h1><p>{{ $return->salesInvoice?->number }} · {{ $return->account?->legal_name }} · {{ $return->statusEnum()->label() }}</p></div>
<div class="page-actions">
@can('sales_returns.manage')
@if($return->statusEnum()->value === 'draft')
<form method="POST" action="{{ route('returns.authorize', $return->getKey()) }}">@csrf<button class="button-primary" type="submit">RMA Yetkilendir</button></form>
<form method="POST" action="{{ route('returns.cancel', $return->getKey()) }}">@csrf<button type="submit">İptal Et</button></form>
@elseif($return->statusEnum()->value === 'authorized')
<form method="POST" action="{{ route('returns.cancel', $return->getKey()) }}">@csrf<button type="submit">İptal Et</button></form>
@elseif($return->statusEnum()->value === 'received')
<form method="POST" action="{{ route('returns.complete', $return->getKey()) }}">@csrf<button class="button-primary" type="submit">RMA Tamamla</button></form>
@endif
@endcan
</div>
</section>
<section class="detail-card"><div class="form-grid"><div><strong>İade Tarihi</strong><p>{{ $return->return_date?->format('d.m.Y') }}</p></div><div><strong>Para Birimi</strong><p>{{ $return->currency_code }}</p></div><div><strong>Talep Brüt</strong><p>{{ $return->requested_gross_total }} {{ $return->currency_code }}</p></div><div><strong>Kredilendirilecek Brüt</strong><p>{{ $return->credited_gross_total }} {{ $return->currency_code }}</p></div><div><strong>Not</strong><p>{{ $return->note ?: '—' }}</p></div></div></section>

@if($return->statusEnum()->value === 'authorized')
@can('sales_returns.manage')
<form method="POST" action="{{ route('returns.receive', $return->getKey()) }}">@csrf
<section class="detail-card"><h2>Fiziksel Kabul / Kalite Kontrolü</h2><p>Kabul + red, talep miktarına eşit olmalıdır. Stoğa dönüş miktarı kabul edileni aşamaz; stoğa dönüş orijinal satış çıkış maliyetiyle değerlenir.</p></section>
<section class="statement-table-card"><table class="data-table"><thead><tr><th>Ürün</th><th>Talep</th><th>Kabul</th><th>Red</th><th>Stoğa Dönüş</th><th>Kontrol Notu</th></tr></thead><tbody>
@foreach($return->lines as $row => $line)
<tr><td>{{ $line->product_code }} — {{ $line->product_name }}<input type="hidden" name="lines[{{ $row }}][sales_return_line_id]" value="{{ $line->getKey() }}"></td><td>{{ $line->quantity }}</td><td><input name="lines[{{ $row }}][accepted_quantity]" value="{{ old('lines.'.$row.'.accepted_quantity', $line->quantity) }}" inputmode="decimal"></td><td><input name="lines[{{ $row }}][rejected_quantity]" value="{{ old('lines.'.$row.'.rejected_quantity', '0') }}" inputmode="decimal"></td><td><input name="lines[{{ $row }}][restock_quantity]" value="{{ old('lines.'.$row.'.restock_quantity', $line->quantity) }}" inputmode="decimal"></td><td><input name="lines[{{ $row }}][condition_notes]" value="{{ old('lines.'.$row.'.condition_notes') }}" maxlength="1000"></td></tr>
@endforeach
</tbody></table></section>
<div class="page-actions"><button class="button-primary" type="submit">Kabul Kontrolünü Kaydet</button></div>
</form>
@endcan
@else
<section class="statement-table-card"><table class="data-table"><thead><tr><th>Ürün</th><th>Fatura Satırı</th><th>Neden</th><th>Talep</th><th>Kabul</th><th>Red</th><th>Stoğa Dönüş</th><th>Kredi Brüt</th><th>Maliyet</th></tr></thead><tbody>
@foreach($return->lines as $line)<tr><td>{{ $line->product_code }} — {{ $line->product_name }}</td><td>#{{ $line->sales_invoice_line_id }}</td><td>{{ $line->reason_code }}</td><td>{{ $line->quantity }}</td><td>{{ $line->accepted_quantity }}</td><td>{{ $line->rejected_quantity }}</td><td>{{ $line->restock_quantity }}</td><td>{{ $line->credited_gross }}</td><td>{{ $line->unit_cost ?? '—' }}</td></tr>@endforeach
</tbody></table></section>
@endif
@endsection
