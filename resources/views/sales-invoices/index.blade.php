@extends('layouts.app')

@section('title', 'Satış Faturaları')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Faturalar</p>
        <h1>Satış Faturaları</h1>
        <p>Doğrudan, sipariş bağlı ve irsaliye bağlı taslak faturalar; ticari toplamlar deterministik calculator authority'sinden üretilir.</p>
    </div>
    <div class="page-actions">
        @can('sales_invoices.manage')<a href="{{ route('sales-invoices.create') }}">Yeni Satış Faturası</a>@endcan
    </div>
</section>

<section class="detail-card">
    <form method="GET" action="{{ route('sales-invoices.index') }}" class="form-grid">
        <label>Arama
            <input type="search" name="q" value="{{ $search }}" placeholder="Fatura, cari, sipariş veya irsaliye no">
        </label>
        <div class="page-actions"><button type="submit">Ara</button></div>
    </form>
</section>

<section class="statement-table-card">
<table class="data-table">
    <thead><tr><th>Fatura</th><th>Tarih</th><th>Mod</th><th>Cari</th><th>Kaynak</th><th>Net</th><th>KDV</th><th>Toplam</th><th>Durum</th></tr></thead>
    <tbody>
    @forelse($invoices as $invoice)
        <tr>
            <td><a href="{{ route('sales-invoices.show', $invoice->getKey()) }}">{{ $invoice->number }}</a></td>
            <td>{{ $invoice->invoice_date?->format('d.m.Y') }}</td>
            <td>{{ $invoice->modeEnum()->label() }}</td>
            <td>{{ $invoice->customer_legal_name }}</td>
            <td>
                @if($invoice->sourceDispatch)İrsaliye {{ $invoice->sourceDispatch->number }}
                @elseif($invoice->sourceSalesOrder)Sipariş {{ $invoice->sourceSalesOrder->number }}
                @else Doğrudan
                @endif
            </td>
            <td>{{ $invoice->net_total }} {{ $invoice->currency_code }}</td>
            <td>{{ $invoice->tax_total }} {{ $invoice->currency_code }}</td>
            <td>{{ $invoice->gross_total }} {{ $invoice->currency_code }}</td>
            <td>{{ $invoice->statusEnum()->label() }}</td>
        </tr>
    @empty
        <tr><td colspan="9">Kayıt bulunamadı.</td></tr>
    @endforelse
    </tbody>
</table>
</section>

{{ $invoices->links() }}
@endsection
