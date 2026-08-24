@extends('layouts.settings')

@section('title', 'Roller ve Yetkiler')
@section('heading', 'Roller ve Yetkiler')

@section('content')
<div class="page-actions">
    <p>Aktif şirkete ait roller ve yetki kapsamları.</p>
    @can('core.role.manage')
        <a class="button-primary" href="{{ route('settings.roles.create') }}">Yeni Rol</a>
    @endcan
</div>

<div class="table-card">
    <table>
        <thead><tr><th>Rol</th><th>Kod</th><th>Durum</th><th>Yetki</th><th>Kullanıcı</th><th></th></tr></thead>
        <tbody>
        @forelse ($roles as $role)
            <tr>
                <td>{{ $role->name }}</td>
                <td>{{ $role->code }}</td>
                <td>{{ $role->is_active ? 'Aktif' : 'Pasif' }}</td>
                <td>{{ $role->permissions_count }}</td>
                <td>{{ $role->memberships_count }}</td>
                <td><a href="{{ route('settings.roles.show', $role->getKey()) }}">Detay</a></td>
            </tr>
        @empty
            <tr><td colspan="6">Bu şirkette rol bulunmuyor.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
