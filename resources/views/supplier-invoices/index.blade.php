@extends('layouts.app')

@section('title', 'Alış Faturaları')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Alış / Faturalar</p><h1>Alış Faturaları</h1><p>Satınalma siparişi faturalama progress ve tedarikçi cari etkisi.</p></div>
    <div class="page-actions">@can('supplier_invoices.manage')<a class="button-primary" href="{{ route('supplier-invoices.create') }}">Yeni Alış Faturası</a>@endcan</div>
</section>

<section class="detail-card">
<form method="GET" action="{{ route('supplier-invoices.index') }}" class="form-grid"><div><label for="q">Ara</label><input id="q" name="q" value="{{ $search }}" placeholder="Fatura, sipariş veya tedarikçi"></div><div class="page-actions"><button type="submit">Ara</button></div></form>
</section>

<section class="statement-table-card"><table class="data-table"><thead><tr><th>Fatura</th><th>Tedarikçi</th><th>Kaynak Sipariş</th><th>Tarih</th><th>Durum</th><th>Genel Toplam</th></tr></thead><tbody>
@forelse($invoices as $invoice)
<tr><td><a href="{{ route('supplier-invoices.show', $invoice->getKey()) }}">{{ $invoice->number }}</a></td><td>{{ $invoice->account?->legal_name }}</td><td>{{ $invoice->purchaseOrder?->number }}</td><td>{{ $invoice->invoice_date?->format('d.m.Y') }}</td><td>{{ $invoice->statusEnum()->label() }}</td><td>{{ $invoice->gross_total }} {{ $invoice->currency_code }}</td></tr>
@empty<tr><td colspan="6">Alış faturası bulunamadı.</td></tr>@endforelse
</tbody></table></section>
{{ $invoices->links() }}
@endsection
