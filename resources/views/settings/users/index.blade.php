@extends('layouts.settings')

@section('title', 'Kullanıcılar')
@section('heading', 'Kullanıcılar')

@section('content')
<div class="page-actions">
    <p>Aktif şirket kullanıcıları ve üyelik durumları.</p>
    @can('core.user.manage')
        <a class="button-primary" href="{{ route('settings.users.create') }}">Yeni Kullanıcı</a>
    @endcan
</div>

<div class="table-card">
    <table>
        <thead>
        <tr><th>Ad</th><th>E-posta</th><th>Durum</th><th>Roller</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($memberships as $membership)
            <tr>
                <td>{{ $membership->user?->name ?? '—' }}</td>
                <td>{{ $membership->user?->email ?? '—' }}</td>
                <td>{{ $membership->is_active ? 'Aktif' : 'Pasif' }}</td>
                <td>{{ $membership->roles->pluck('name')->join(', ') ?: 'Rol yok' }}</td>
                <td><a href="{{ route('settings.users.show', $membership->getKey()) }}">Detay</a></td>
            </tr>
        @empty
            <tr><td colspan="5">Bu şirkette kullanıcı bulunmuyor.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
