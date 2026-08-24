@extends('layouts.settings')

@section('title', 'İşlem Kaydı Detayı')
@section('heading', 'İşlem Kaydı Detayı')

@section('content')
    <div class="page-actions">
        <p>Audit kaydı salt okunurdur ve normal uygulama akışıyla değiştirilemez.</p>
        <a href="{{ route('settings.audit.index') }}">Listeye Dön</a>
    </div>

    <section class="detail-card">
        <dl class="detail-grid">
            <div><dt>İşlem</dt><dd>{{ $entry->actionLabel() }}</dd></div>
            <div><dt>Hedef</dt><dd>{{ $entry->targetLabel() }}{{ $entry->target_id !== null ? ' #'.$entry->target_id : '' }}</dd></div>
            <div><dt>Kullanıcı</dt><dd>{{ $entry->actorUser?->name ?? 'Sistem' }}</dd></div>
            <div><dt>Kaynak</dt><dd>{{ $entry->source }}</dd></div>
            <div><dt>Zaman</dt><dd>{{ $entry->occurred_at?->setTimezone($timezone)->format('d.m.Y H:i:s') }}</dd></div>
            <div><dt>Correlation ID</dt><dd>{{ $entry->correlation_id }}</dd></div>
            <div><dt>Event ID</dt><dd>{{ $entry->event_id }}</dd></div>
        </dl>
    </section>

    <section class="detail-card">
        <h2>Önce</h2>
        <pre>{{ $entry->before_state === null ? '—' : json_encode($entry->before_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>

    <section class="detail-card">
        <h2>Sonra</h2>
        <pre>{{ $entry->after_state === null ? '—' : json_encode($entry->after_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>

    @if ($entry->metadata !== [])
        <section class="detail-card">
            <h2>Ek Bilgi</h2>
            <pre>{{ json_encode($entry->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </section>
    @endif
@endsection
