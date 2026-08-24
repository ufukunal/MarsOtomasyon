@extends('layouts.settings')

@section('title', 'Vergi Detayı')
@section('heading', 'Vergi Detayı')

@section('content')
<div class="page-actions">
    <p>Vergi tanımı salt okunur görünüm.</p>
    @can('core.settings.manage')
        <a class="button-primary" href="{{ route('settings.taxes.edit', $tax) }}">Düzenle</a>
    @endcan
</div>
<section class="detail-card">
    <dl class="detail-grid">
        <div><dt>Kod</dt><dd>{{ $tax->code }}</dd></div>
        <div><dt>Ad</dt><dd>{{ $tax->name }}</dd></div>
        <div><dt>Oran</dt><dd>%{{ rtrim(rtrim($tax->rate, '0'), '.') }}</dd></div>
        <div><dt>Durum</dt><dd>{{ $tax->is_active ? 'Aktif' : 'Pasif' }}</dd></div>
    </dl>
</section>
<a href="{{ route('settings.taxes.index') }}">Vergi / KDV listesine dön</a>
@endsection
