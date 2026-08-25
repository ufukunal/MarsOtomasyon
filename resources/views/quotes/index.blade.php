@extends('layouts.app')

@section('title', 'Teklifler')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Teklif</p>
        <h1>Teklifler</h1>
        <p>Aktif firmaya ait deterministik vergi hesaplı teklif kayıtları.</p>
    </div>
    @can('quotes.manage')
        <div class="page-actions"><a class="button-primary" href="{{ route('quotes.create') }}" data-workspace-link>Yeni Teklif</a></div>
    @endcan
</section>

<form method="get" action="{{ route('quotes.index') }}" class="detail-card">
    <div class="form-grid">
        <label>Ara<input type="search" name="q" value="{{ $search }}" placeholder="Teklif no veya cari" data-dirty-ignore></label>
        <label>Durum
            <select name="status" data-dirty-ignore>
                <option value="all" @selected($statusFilter === 'all')>Tümü</option>
                <option value="draft" @selected($statusFilter === 'draft')>Taslak</option>
                <option value="cancelled" @selected($statusFilter === 'cancelled')>İptal</option>
            </select>
        </label>
    </div>
    <div class="page-actions"><span></span><button type="submit">Filtrele</button></div>
</form>

<section class="detail-card statement-table-card">
<table class="data-table">
<thead><tr><th>No</th><th>Tarih</th><th>Cari</th><th>Para Birimi</th><th class="amount-cell">Net</th><th class="amount-cell">KDV</th><th class="amount-cell">Genel Toplam</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
@forelse($quotes as $quote)
<tr>
    <td>{{ $quote->number }}</td><td>{{ $quote->quote_date->format('d.m.Y') }}</td><td>{{ $quote->account->legal_name }}</td><td>{{ $quote->currency_code }}</td>
    <td class="amount-cell">{{ $quote->net_total }}</td><td class="amount-cell">{{ $quote->tax_total }}</td><td class="amount-cell">{{ $quote->gross_total }}</td>
    <td>{{ $quote->statusEnum()->label() }}</td><td><a href="{{ route('quotes.show', $quote->getKey()) }}" data-workspace-link>Detay</a></td>
</tr>
@empty
<tr><td colspan="9">Teklif kaydı bulunamadı.</td></tr>
@endforelse
</tbody>
</table>
</section>
{{ $quotes->links() }}
@endsection
