@extends('layouts.settings')

@section('title', 'Rol Detayı')
@section('heading', 'Rol Detayı')

@section('content')
<div class="page-actions">
    <a href="{{ route('settings.roles.index') }}">← Roller ve Yetkiler</a>
    @can('core.role.manage')
        <a class="button-primary" href="{{ route('settings.roles.edit', $role->getKey()) }}">Düzenle</a>
    @endcan
</div>

<div class="detail-card">
    <dl class="detail-grid">
        <div><dt>Rol</dt><dd>{{ $role->name }}</dd></div>
        <div><dt>Kod</dt><dd>{{ $role->code }}</dd></div>
        <div><dt>Durum</dt><dd>{{ $role->is_active ? 'Aktif' : 'Pasif' }}</dd></div>
        <div><dt>Atanmış kullanıcı</dt><dd>{{ $role->memberships->count() }}</dd></div>
    </dl>

    <h2>Yetkiler</h2>
    <ul class="plain-list">
        @forelse ($role->permissions as $permission)
            <li><strong>{{ $permission->name }}</strong><span>{{ $permission->key }}</span></li>
        @empty
            <li>Bu role yetki atanmadı.</li>
        @endforelse
    </ul>
</div>
@endsection
