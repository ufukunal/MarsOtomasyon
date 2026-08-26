@extends('layouts.app')

@section('title', 'İrsaliyeler')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Satış / İrsaliye</p><h1>İrsaliyeler</h1><p>Satış siparişi kaynaklı taslak sevkiyat belgeleri, adres ve taşıyıcı metadata bilgileriyle izlenir.</p></div>
    <div class="page-actions">
        @can('sales_orders.view')<a href="{{ route('sales-orders.index') }}">Siparişler</a>@endcan
        @can('dispatches.manage')<a class="button-primary" href="{{ route('dispatches.create') }}">Yeni İrsaliye</a>@endcan
    </div>
</section>

<form method="get" class="detail-card">
    <div class="form-grid">
        <label>Ara<input name="q" value="{{ $search }}" placeholder="İrsaliye no, sipariş no, cari veya taşıyıcı"></label>
    </div>
    <div class="page-actions"><button class="button-secondary" type="submit">Filtrele</button></div>
</form>

<section class="statement-table-card">
<table class="data-table"><thead><tr><th>No</th><th>Tarih</th><th>Sipariş</th><th>Cari</th><th>Taşıyıcı</th><th>Takip No</th><th>Durum</th></tr></thead><tbody>
@forelse($dispatches as $dispatch)
<tr>
    <td><a href="{{ route('dispatches.show', $dispatch->getKey()) }}">{{ $dispatch->number }}</a></td>
    <td>{{ $dispatch->dispatch_date?->format('d.m.Y') }}</td>
    <td>
        @can('sales_orders.view')
            <a href="{{ route('sales-orders.show', $dispatch->sales_order_id) }}">{{ $dispatch->salesOrder?->number }}</a>
        @else
            {{ $dispatch->salesOrder?->number }}
        @endcan
    </td>
    <td>{{ $dispatch->account?->legal_name }}</td>
    <td>{{ $dispatch->carrier_name ?? '—' }}</td>
    <td>{{ $dispatch->tracking_number ?? '—' }}</td>
    <td>{{ $dispatch->statusEnum()->label() }}</td>
</tr>
@empty<tr><td colspan="7">İrsaliye bulunamadı.</td></tr>@endforelse
</tbody></table>
</section>
{{ $dispatches->links() }}
@endsection
