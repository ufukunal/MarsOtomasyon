@extends('layouts.settings')

@section('title', $membership ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı')
@section('heading', $membership ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı')

@section('content')
<div class="page-actions">
    <a href="{{ $membership ? route('settings.users.show', $membership->getKey()) : route('settings.users.index') }}">← Geri</a>
</div>

<form class="form-card" method="post" action="{{ $membership ? route('settings.users.update', $membership->getKey()) : route('settings.users.store') }}">
    @csrf
    @if ($membership)
        @method('put')
    @endif

    @if ($identityEditable)
        <label class="auth-field">
            <span>Ad</span>
            <input name="name" value="{{ old('name', $membership?->user?->name) }}" required maxlength="160">
        </label>
        <label class="auth-field">
            <span>E-posta</span>
            <input type="email" name="email" value="{{ old('email', $membership?->user?->email) }}" required maxlength="255">
        </label>
        <label class="auth-field">
            <span>{{ $membership ? 'Yeni parola (opsiyonel)' : 'Parola' }}</span>
            <input type="password" name="password" {{ $membership ? '' : 'required' }} minlength="12" autocomplete="new-password">
        </label>
    @else
        <div class="notice-info">Bu kullanıcı başka şirketlere de bağlı. Global ad/e-posta/parola alanları bu şirket ekranından değiştirilemez.</div>
    @endif

    @if ($membership)
        <label class="auth-field">
            <span>Şirket üyeliği</span>
            <select name="is_active">
                <option value="1" @selected(old('is_active', $membership->is_active ? '1' : '0') === '1')>Aktif</option>
                <option value="0" @selected(old('is_active', $membership->is_active ? '1' : '0') === '0')>Pasif</option>
            </select>
        </label>
    @endif

    <fieldset class="checkbox-group">
        <legend>Roller</legend>
        @forelse ($roles as $role)
            <label>
                <input type="checkbox" name="role_ids[]" value="{{ $role->getKey() }}" @checked(in_array($role->getKey(), old('role_ids', $selectedRoleIds), true))>
                <span><strong>{{ $role->name }}</strong> · {{ $role->code }}</span>
            </label>
        @empty
            <p>Atayabileceğiniz aktif rol bulunmuyor.</p>
        @endforelse
    </fieldset>

    <button class="button-primary" type="submit">Kaydet</button>
</form>
@endsection
