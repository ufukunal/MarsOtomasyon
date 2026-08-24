@extends('layouts.settings')

@section('title', 'Vergi / KDV')
@section('heading', 'Vergi / KDV')

@section('content')
<div class="page-actions">
    <p>Belge satırlarında kullanılacak vergi oranları ve KDV sıfır nedenleri.</p>
    @can('core.settings.manage')
        <div class="settings-nav">
            <a class="button-primary" href="{{ route('settings.taxes.create') }}">Yeni Vergi</a>
            <a class="button-primary" href="{{ route('settings.tax-zero-reasons.create') }}">Yeni Sıfır Nedeni</a>
        </div>
    @endcan
</div>

<section class="table-card">
    <h2>Vergi Tanımları</h2>
    <table>
        <thead><tr><th>Kod</th><th>Ad</th><th>Oran</th><th>Durum</th></tr></thead>
        <tbody>
        @forelse ($taxes as $tax)
            <tr>
                <td><a href="{{ route('settings.taxes.show', $tax) }}">{{ $tax->code }}</a></td>
                <td>{{ $tax->name }}</td>
                <td>%{{ rtrim(rtrim($tax->rate, '0'), '.') }}</td>
                <td>{{ $tax->is_active ? 'Aktif' : 'Pasif' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Henüz vergi tanımı yok.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="table-card">
    <h2>KDV Sıfır Nedenleri</h2>
    <table>
        <thead><tr><th>Kod</th><th>Açıklama</th><th>Durum</th></tr></thead>
        <tbody>
        @forelse ($zeroReasons as $reason)
            <tr>
                <td><a href="{{ route('settings.tax-zero-reasons.show', $reason) }}">{{ $reason->code }}</a></td>
                <td>{{ $reason->name }}</td>
                <td>{{ $reason->is_active ? 'Aktif' : 'Pasif' }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Henüz KDV sıfır nedeni yok.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
