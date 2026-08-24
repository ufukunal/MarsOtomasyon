@extends('layouts.settings')

@section('title', $rate ? 'Kur Düzenle' : 'Yeni Kur')
@section('heading', $rate ? 'Kur Düzenle' : 'Yeni Kur')

@section('content')
<form class="form-card" method="post" action="{{ $rate ? route('settings.exchange-rates.update', $rate) : route('settings.exchange-rates.store') }}">
    @csrf
    @if ($rate) @method('put') @endif

    @if ($rate)
        <div class="notice-info">Tarih ve para birimi çifti kayıt kimliğidir; düzenlenemez.</div>
        <dl class="detail-grid">
            <div><dt>Tarih</dt><dd>{{ $rate->rate_date?->format('d.m.Y') }}</dd></div>
            <div><dt>Çift</dt><dd>{{ $rate->from_currency_code }} → {{ $rate->to_currency_code }}</dd></div>
        </dl>
    @else
        <label class="auth-field">Tarih
            <input type="date" name="rate_date" required value="{{ old('rate_date') }}">
        </label>
        <label class="auth-field">Kaynak Para Birimi
            <select name="from_currency_code" required>
                <option value="">Seçin</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->code }}" @selected(old('from_currency_code') === $currency->code)>{{ $currency->code }} · {{ $currency->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="auth-field">Hedef Para Birimi
            <select name="to_currency_code" required>
                <option value="">Seçin</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->code }}" @selected(old('to_currency_code') === $currency->code)>{{ $currency->code }} · {{ $currency->name }}</option>
                @endforeach
            </select>
        </label>
    @endif

    <label class="auth-field">Kur
        <input name="rate" required inputmode="decimal" value="{{ old('rate', $rate?->rate) }}" placeholder="1.0000000000">
    </label>
    <label class="auth-field">Kaynak
        <input name="source" required maxlength="32" value="{{ old('source', $rate?->source ?? 'manual') }}">
    </label>

    <div class="settings-nav">
        <button class="button-primary" type="submit">Kaydet</button>
        <a href="{{ $rate ? route('settings.exchange-rates.show', $rate) : route('settings.exchange-rates.index') }}">Vazgeç</a>
    </div>
</form>
@endsection
