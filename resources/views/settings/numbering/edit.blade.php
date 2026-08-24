@extends('layouts.settings')

@section('title', 'Numara Serisi Düzenle')
@section('heading', 'Numara Serisi Düzenle')

@section('content')
    <div class="page-actions">
        <p>{{ $sequence->document_type->label() }} · {{ $sequence->series_code }}</p>
        <a href="{{ route('settings.numbering.show', $sequence->getKey()) }}">Detaya Dön</a>
    </div>

    <form class="form-card" method="post" action="{{ route('settings.numbering.update', $sequence->getKey()) }}">
        @csrf
        @method('put')
        <input type="hidden" name="is_active" value="0">

        <label class="auth-field">
            Belge / Seri
            <input type="text" value="{{ $sequence->document_type->label() }} · {{ $sequence->series_code }}" disabled>
        </label>

        <label class="auth-field">
            Önek
            <input name="prefix" maxlength="32" value="{{ old('prefix', $sequence->prefix) }}">
        </label>

        <label class="auth-field">
            Basamak Sayısı
            <input name="padding" type="number" min="1" max="18" value="{{ old('padding', $sequence->padding) }}" required>
        </label>

        <label class="auth-field">
            Sonraki Sıra Değeri
            <input name="next_value" type="number" min="{{ $sequence->next_value }}" value="{{ old('next_value', $sequence->next_value) }}" required>
            <small>Geri alınamaz; yalnız mevcut değerden daha ileri bir sıraya taşınabilir.</small>
        </label>

        <label>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sequence->is_active ? '1' : '0') === '1')>
            Aktif
        </label>

        <div class="page-actions">
            <a href="{{ route('settings.numbering.show', $sequence->getKey()) }}">Vazgeç</a>
            <button class="button-primary" type="submit">Kaydet</button>
        </div>
    </form>
@endsection
