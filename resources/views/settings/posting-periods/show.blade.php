@extends('layouts.settings')

@section('title', 'Dönem Detayı')
@section('heading', 'Dönem Detayı')

@section('content')
<div class="page-actions">
    <p>Muhasebe dönemi salt okunur görünüm.</p>
    @can('core.settings.manage')
        @if ($period->status === \App\Modules\Core\Enums\PostingPeriodStatus::Open)
            <div class="settings-nav">
                <a class="button-primary" href="{{ route('settings.posting-periods.edit', $period) }}">Düzenle</a>
                <form method="post" action="{{ route('settings.posting-periods.close', $period) }}">
                    @csrf
                    <button class="button-primary" type="submit">Dönemi Kapat</button>
                </form>
            </div>
        @endif
    @endcan
</div>
<section class="detail-card">
    <dl class="detail-grid">
        <div><dt>Kod</dt><dd>{{ $period->code }}</dd></div>
        <div><dt>Ad</dt><dd>{{ $period->name }}</dd></div>
        <div><dt>Başlangıç</dt><dd>{{ $period->starts_on?->format('d.m.Y') }}</dd></div>
        <div><dt>Bitiş</dt><dd>{{ $period->ends_on?->format('d.m.Y') }}</dd></div>
        <div><dt>Durum</dt><dd>{{ $period->status->label() }}</dd></div>
        <div><dt>Kapanış</dt><dd>{{ $period->closed_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
    </dl>
</section>
@if ($period->status === \App\Modules\Core\Enums\PostingPeriodStatus::Closed)
    <div class="notice-info">Kapalı dönem normal yönetim ekranından yeniden açılamaz veya düzenlenemez.</div>
@endif
<a href="{{ route('settings.posting-periods.index') }}">Dönemler listesine dön</a>
@endsection
