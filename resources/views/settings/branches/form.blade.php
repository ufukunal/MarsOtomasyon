@extends('layouts.settings')

@section('title', $branch ? 'Şube Düzenle' : 'Yeni Şube')
@section('heading', $branch ? 'Şube Düzenle' : 'Yeni Şube')

@section('content')
    <div class="page-actions">
        <p>{{ $branch ? 'Şube bilgilerini düzenleyin.' : 'Aktif şirkete yeni operasyon şubesi ekleyin.' }}</p>
        <a href="{{ $branch ? route('settings.branches.show', $branch->getKey()) : route('settings.branches.index') }}">Vazgeç</a>
    </div>

    <form method="post" action="{{ $branch ? route('settings.branches.update', $branch->getKey()) : route('settings.branches.store') }}" class="detail-card">
        @csrf
        @if ($branch)
            @method('PUT')
        @endif

        <div class="form-grid">
            <label>
                Şube Kodu
                <input name="code" maxlength="32" required value="{{ old('code', $branch?->code) }}">
            </label>

            <label>
                Şube Adı
                <input name="name" maxlength="160" required value="{{ old('name', $branch?->name) }}">
            </label>

            <label>
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $branch?->is_active ?? true))>
                Aktif
            </label>
        </div>

        <div class="page-actions">
            <span></span>
            <button type="submit">Kaydet</button>
        </div>
    </form>
@endsection
