@extends('layouts.settings')

@section('title', 'KDV Sıfır Nedeni')
@section('heading', 'KDV Sıfır Nedeni')

@section('content')
<div class="page-actions">
    <p>KDV sıfır nedeni salt okunur görünüm.</p>
    @can('core.settings.manage')
        <a class="button-primary" href="{{ route('settings.tax-zero-reasons.edit', $zeroReason) }}">Düzenle</a>
    @endcan
</div>
<section class="detail-card">
    <dl class="detail-grid">
        <div><dt>Kod</dt><dd>{{ $zeroReason->code }}</dd></div>
        <div><dt>Açıklama</dt><dd>{{ $zeroReason->name }}</dd></div>
        <div><dt>Durum</dt><dd>{{ $zeroReason->is_active ? 'Aktif' : 'Pasif' }}</dd></div>
    </dl>
</section>
<a href="{{ route('settings.taxes.index') }}">Vergi / KDV listesine dön</a>
@endsection
