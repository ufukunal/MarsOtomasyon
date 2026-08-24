@extends('layouts.settings')

@section('title', 'İşlem Geçmişi')
@section('heading', 'İşlem Geçmişi')

@section('content')
    <div class="page-actions">
        <p>Kritik yönetim değişikliklerinin salt okunur denetim kaydı. Son 200 kayıt gösterilir.</p>
    </div>

    <section class="list-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>Zaman</th>
                <th>İşlem</th>
                <th>Hedef</th>
                <th>Kullanıcı</th>
                <th>Correlation ID</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td><a href="{{ route('settings.audit.show', $entry->getKey()) }}">{{ $entry->occurred_at?->setTimezone($timezone)->format('d.m.Y H:i:s') }}</a></td>
                    <td>{{ $entry->actionLabel() }}</td>
                    <td>{{ $entry->targetLabel() }}{{ $entry->target_id !== null ? ' #'.$entry->target_id : '' }}</td>
                    <td>{{ $entry->actorUser?->name ?? 'Sistem' }}</td>
                    <td>{{ $entry->correlation_id }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Henüz audit kaydı yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
