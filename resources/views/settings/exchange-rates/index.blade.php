@extends('layouts.settings')

@section('title', 'Para Birimi / Kur')
@section('heading', 'Para Birimi / Kur')

@section('content')
<div class="page-actions">
    <p>Şirket para birimi referansları ve manuel kur kayıtları.</p>
    @can('core.settings.manage')
        <a class="button-primary" href="{{ route('settings.exchange-rates.create') }}">Yeni Kur</a>
    @endcan
</div>

<section class="table-card">
    <h2>Para Birimleri</h2>
    <table>
        <thead><tr><th>Kod</th><th>Ad</th><th>Ondalık Basamak</th></tr></thead>
        <tbody>
        @foreach ($currencies as $currency)
            <tr><td>{{ $currency->code }}</td><td>{{ $currency->name }}</td><td>{{ $currency->minor_unit }}</td></tr>
        @endforeach
        </tbody>
    </table>
</section>

<section class="table-card">
    <h2>Kur Kayıtları</h2>
    <table>
        <thead><tr><th>Tarih</th><th>Çift</th><th>Kur</th><th>Kaynak</th></tr></thead>
        <tbody>
        @forelse ($rates as $rate)
            <tr>
                <td><a href="{{ route('settings.exchange-rates.show', $rate) }}">{{ $rate->rate_date?->format('d.m.Y') }}</a></td>
                <td>{{ $rate->from_currency_code }} → {{ $rate->to_currency_code }}</td>
                <td>{{ rtrim(rtrim($rate->rate, '0'), '.') }}</td>
                <td>{{ $rate->source }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Henüz kur kaydı yok.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
