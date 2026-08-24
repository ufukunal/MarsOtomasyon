@extends('layouts.settings')

@section('title', 'Yeni Numara Serisi')
@section('heading', 'Yeni Numara Serisi')

@section('content')
    <div class="page-actions">
        <p>Belge türü için yeni bir numara serisi oluşturun.</p>
        <a href="{{ route('settings.numbering.index') }}">Listeye Dön</a>
    </div>

    <form class="form-card" method="post" action="{{ route('settings.numbering.store') }}">
        @csrf
        <input type="hidden" name="is_active" value="0">

        <label class="auth-field">
            Belge Türü
            <select name="document_type" required>
                <option value="">Seçin</option>
                @foreach ($documentTypes as $documentType)
                    <option value="{{ $documentType->value }}" @selected(old('document_type') === $documentType->value)>{{ $documentType->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="auth-field">
            Seri Kodu
            <input name="series_code" maxlength="32" value="{{ old('series_code', 'default') }}" required>
            <small>Örnek: default, magaza, ihracat.</small>
        </label>

        <label class="auth-field">
            Önek
            <input name="prefix" maxlength="32" value="{{ old('prefix') }}">
            <small>Örnek: SIP-, IRS-, FTR-.</small>
        </label>

        <label class="auth-field">
            Basamak Sayısı
            <input name="padding" type="number" min="1" max="18" value="{{ old('padding', 6) }}" required>
        </label>

        <label class="auth-field">
            İlk Numara
            <input name="next_value" type="number" min="1" value="{{ old('next_value', 1) }}" required>
        </label>

        <label>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')>
            Aktif
        </label>

        <div class="page-actions">
            <a href="{{ route('settings.numbering.index') }}">Vazgeç</a>
            <button class="button-primary" type="submit">Oluştur</button>
        </div>
    </form>
@endsection
