@extends('layouts.app')

@section('title', $unit ? 'Birim Düzenle' : 'Yeni Birim')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Katalog</p>
            <h1>{{ $unit ? 'Birim Düzenle' : 'Yeni Birim' }}</h1>
            <p>Birim kodu firma içinde tekildir. Pasife alınan birim mevcut ürün ilişkisini bozmaz.</p>
        </div>
        <a href="{{ route('inventory.units.index') }}" data-workspace-link>Listeye Dön</a>
    </section>

    @if ($errors->any())
        <div class="notice-error" role="alert">
            <strong>Kayıt tamamlanamadı.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ $unit ? route('inventory.units.update', $unit->getKey()) : route('inventory.units.store') }}" class="detail-card">
        @csrf
        @if ($unit)
            @method('PUT')
        @endif

        <div class="form-grid">
            <label>
                Birim Kodu
                <input name="code" maxlength="32" required value="{{ old('code', $unit?->code) }}">
            </label>
            <label>
                Birim Adı
                <input name="name" maxlength="80" required value="{{ old('name', $unit?->name) }}">
            </label>
            <label>
                Durum
                <select name="status" required>
                    <option value="active" @selected(old('status', $unit === null || $unit->is_active ? 'active' : 'inactive') === 'active')>Aktif</option>
                    <option value="inactive" @selected(old('status', $unit === null || $unit->is_active ? 'active' : 'inactive') === 'inactive')>Pasif</option>
                </select>
            </label>
        </div>

        <div class="page-actions">
            <span></span>
            <button type="submit">Kaydet</button>
        </div>
    </form>
@endsection
