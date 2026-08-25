@extends('layouts.app')

@section('title', $category ? 'Kategori Düzenle' : 'Yeni Kategori')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Katalog</p>
            <h1>{{ $category ? 'Kategori Düzenle' : 'Yeni Kategori' }}</h1>
            <p>Kategori kodu firma içinde tekildir. Pasife alınan kategori mevcut ürünlerden silinmez.</p>
        </div>
        <a href="{{ route('inventory.categories.index') }}" data-workspace-link>Listeye Dön</a>
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

    <form method="post" action="{{ $category ? route('inventory.categories.update', $category->getKey()) : route('inventory.categories.store') }}" class="detail-card">
        @csrf
        @if ($category)
            @method('PUT')
        @endif

        <div class="form-grid">
            <label>
                Kategori Kodu
                <input name="code" maxlength="64" required value="{{ old('code', $category?->code) }}">
            </label>
            <label>
                Kategori Adı
                <input name="name" maxlength="160" required value="{{ old('name', $category?->name) }}">
            </label>
            <label>
                Durum
                <select name="status" required>
                    <option value="active" @selected(old('status', $category === null || $category->is_active ? 'active' : 'inactive') === 'active')>Aktif</option>
                    <option value="inactive" @selected(old('status', $category === null || $category->is_active ? 'active' : 'inactive') === 'inactive')>Pasif</option>
                </select>
            </label>
        </div>

        <div class="page-actions">
            <span></span>
            <button type="submit">Kaydet</button>
        </div>
    </form>
@endsection
