@extends('layouts.settings')

@section('title', $period ? 'Dönem Düzenle' : 'Yeni Dönem')
@section('heading', $period ? 'Dönem Düzenle' : 'Yeni Dönem')

@section('content')
<form class="form-card" method="post" action="{{ $period ? route('settings.posting-periods.update', $period) : route('settings.posting-periods.store') }}">
    @csrf
    @if ($period) @method('put') @endif

    <label class="auth-field">Kod
        <input name="code" required maxlength="32" value="{{ old('code', $period?->code) }}">
    </label>
    <label class="auth-field">Ad
        <input name="name" required maxlength="120" value="{{ old('name', $period?->name) }}">
    </label>
    <label class="auth-field">Başlangıç
        <input type="date" name="starts_on" required value="{{ old('starts_on', $period?->starts_on?->format('Y-m-d')) }}">
    </label>
    <label class="auth-field">Bitiş
        <input type="date" name="ends_on" required value="{{ old('ends_on', $period?->ends_on?->format('Y-m-d')) }}">
    </label>

    <div class="settings-nav">
        <button class="button-primary" type="submit">Kaydet</button>
        <a href="{{ $period ? route('settings.posting-periods.show', $period) : route('settings.posting-periods.index') }}">Vazgeç</a>
    </div>
</form>
@endsection
