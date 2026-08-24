@extends('layouts.settings')

@section('title', $zeroReason ? 'KDV Sıfır Nedeni Düzenle' : 'Yeni KDV Sıfır Nedeni')
@section('heading', $zeroReason ? 'KDV Sıfır Nedeni Düzenle' : 'Yeni KDV Sıfır Nedeni')

@section('content')
<form class="form-card" method="post" action="{{ $zeroReason ? route('settings.tax-zero-reasons.update', $zeroReason) : route('settings.tax-zero-reasons.store') }}">
    @csrf
    @if ($zeroReason) @method('put') @endif

    <label class="auth-field">Kod
        <input name="code" required maxlength="32" value="{{ old('code', $zeroReason?->code) }}">
    </label>
    <label class="auth-field">Açıklama
        <input name="name" required maxlength="160" value="{{ old('name', $zeroReason?->name) }}">
    </label>
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $zeroReason?->is_active ?? true))> Aktif</label>

    <div class="settings-nav">
        <button class="button-primary" type="submit">Kaydet</button>
        <a href="{{ $zeroReason ? route('settings.tax-zero-reasons.show', $zeroReason) : route('settings.taxes.index') }}">Vazgeç</a>
    </div>
</form>
@endsection
