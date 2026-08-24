@extends('layouts.settings')

@section('title', 'Kur Detayı')
@section('heading', 'Kur Detayı')

@section('content')
<div class="page-actions">
    <p>Kur kaydı salt okunur görünüm.</p>
    @can('core.settings.manage')
        <a class="button-primary" href="{{ route('settings.exchange-rates.edit', $rate) }}">Değeri Düzenle</a>
    @endcan
</div>
<section class="detail-card">
    <dl class="detail-grid">
        <div><dt>Tarih</dt><dd>{{ $rate->rate_date?->format('d.m.Y') }}</dd></div>
        <div><dt>Kaynak</dt><dd>{{ $rate->from_currency_code }}</dd></div>
        <div><dt>Hedef</dt><dd>{{ $rate->to_currency_code }}</dd></div>
        <div><dt>Kur</dt><dd>{{ rtrim(rtrim($rate->rate, '0'), '.') }}</dd></div>
        <div><dt>Kaynak Tipi</dt><dd>{{ $rate->source }}</dd></div>
    </dl>
</section>
<a href="{{ route('settings.exchange-rates.index') }}">Para Birimi / Kur listesine dön</a>
@endsection
