@extends('layouts.settings')

@section('title', $role ? 'Rol Düzenle' : 'Yeni Rol')
@section('heading', $role ? 'Rol Düzenle' : 'Yeni Rol')

@section('content')
<div class="page-actions">
    <a href="{{ $role ? route('settings.roles.show', $role->getKey()) : route('settings.roles.index') }}">← Geri</a>
</div>

<form class="form-card" method="post" action="{{ $role ? route('settings.roles.update', $role->getKey()) : route('settings.roles.store') }}">
    @csrf
    @if ($role)
        @method('put')
    @endif

    <label class="auth-field">
        <span>Rol kodu</span>
        <input name="code" value="{{ old('code', $role?->code) }}" required maxlength="64" pattern="[A-Za-z0-9._-]+">
    </label>
    <label class="auth-field">
        <span>Rol adı</span>
        <input name="name" value="{{ old('name', $role?->name) }}" required maxlength="160">
    </label>
    <label class="auth-field">
        <span>Durum</span>
        <select name="is_active">
            <option value="1" @selected(old('is_active', $role?->is_active === false ? '0' : '1') === '1')>Aktif</option>
            <option value="0" @selected(old('is_active', $role?->is_active === false ? '0' : '1') === '0')>Pasif</option>
        </select>
    </label>

    <fieldset class="checkbox-group">
        <legend>Yetkiler</legend>
        @forelse ($grantablePermissions as $permission)
            <label>
                <input type="checkbox" name="permission_keys[]" value="{{ $permission->value }}" @checked(in_array($permission->value, old('permission_keys', $selectedPermissionKeys), true))>
                <span><strong>{{ $permission->label() }}</strong><small>{{ $permission->value }}</small></span>
            </label>
        @empty
            <p>Atayabileceğiniz yetki bulunmuyor.</p>
        @endforelse
    </fieldset>

    <button class="button-primary" type="submit">Kaydet</button>
</form>
@endsection
