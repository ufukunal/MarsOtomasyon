@extends('layouts.settings')

@section('title', $tax ? 'Vergi Düzenle' : 'Yeni Vergi')
@section('heading', $tax ? 'Vergi Düzenle' : 'Yeni Vergi')

@section('content')
<form class="form-card" method="post" action="{{ $tax ? route('settings.taxes.update', $tax) : route('settings.taxes.store') }}">
    @csrf
    @if ($tax) @method('put') @endif

    <label class="auth-field">Kod
        <input name="code" required maxlength="32" value="{{ old('code', $tax?->code) }}">
    </label>
    <label class="auth-field">Ad
        <input name="name" required maxlength="120" value="{{ old('name', $tax?->name) }}">
    </label>
    <label class="auth-field">Oran (%)
        <input name="rate" required inputmode="decimal" value="{{ old('rate', $tax?->rate) }}" placeholder="20.000000">
    </label>
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tax?->is_active ?? true))> Aktif</label>

    <div class="settings-nav">
        <button class="button-primary" type="submit">Kaydet</button>
        <a href="{{ $tax ? route('settings.taxes.show', $tax) : route('settings.taxes.index') }}">Vazgeç</a>
    </div>
</form>
@endsection
