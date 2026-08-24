@extends('layouts.settings')

@section('title', 'Firma / Sistem Düzenle')
@section('heading', 'Firma / Sistem Düzenle')

@section('content')
    <div class="page-actions">
        <p>Aktif şirketin para birimi ve saat dilimini düzenleyin.</p>
        <a href="{{ route('settings.company.show') }}">Detaya Dön</a>
    </div>

    <form class="form-card" method="post" action="{{ route('settings.company.update') }}">
        @csrf
        @method('put')

        <label class="auth-field">
            Firma
            <input type="text" value="{{ $company->code }} · {{ $company->name }}" disabled>
        </label>

        <label class="auth-field">
            Ana Para Birimi
            <input name="base_currency_code" maxlength="3" autocomplete="off" value="{{ old('base_currency_code', $company->base_currency_code) }}" required>
            <small>Üç harfli para birimi kodu. Örnek: TRY, USD, EUR.</small>
        </label>

        <label class="auth-field">
            Saat Dilimi
            <input name="timezone" maxlength="64" autocomplete="off" value="{{ old('timezone', $company->timezone) }}" required>
            <small>Örnek: Europe/Istanbul.</small>
        </label>

        <div class="page-actions">
            <a href="{{ route('settings.company.show') }}">Vazgeç</a>
            <button class="button-primary" type="submit">Kaydet</button>
        </div>
    </form>
@endsection
