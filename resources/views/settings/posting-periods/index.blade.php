@extends('layouts.settings')

@section('title', 'Dönemler')
@section('heading', 'Dönemler')

@section('content')
<div class="page-actions">
    <p>Ticari ve finansal posting işlemlerinin açık/kapalı tarih sınırları.</p>
    @can('core.settings.manage')
        <a class="button-primary" href="{{ route('settings.posting-periods.create') }}">Yeni Dönem</a>
    @endcan
</div>
<section class="table-card">
    <table>
        <thead><tr><th>Kod</th><th>Ad</th><th>Başlangıç</th><th>Bitiş</th><th>Durum</th></tr></thead>
        <tbody>
        @forelse ($periods as $period)
            <tr>
                <td><a href="{{ route('settings.posting-periods.show', $period) }}">{{ $period->code }}</a></td>
                <td>{{ $period->name }}</td>
                <td>{{ $period->starts_on?->format('d.m.Y') }}</td>
                <td>{{ $period->ends_on?->format('d.m.Y') }}</td>
                <td>{{ $period->status->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Henüz muhasebe dönemi yok.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
