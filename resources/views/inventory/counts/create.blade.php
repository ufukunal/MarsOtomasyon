@extends('layouts.app')

@section('title', 'Yeni Stok Sayımı')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Stok</p>
            <h1>Yeni Stok Sayımı</h1>
            <p>Sayım başlangıcında lokasyonun fiziksel stok ve maliyet snapshot'ı dondurulur.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.counts.index') }}" data-workspace-link>Sayım Listesi</a>
            <a href="{{ route('inventory.stock.index') }}" data-workspace-link>Bakiyeler</a>
        </div>
    </section>

    <form method="post" action="{{ route('inventory.counts.store') }}" class="detail-card">
        @csrf
        <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">
        <div class="form-grid">
            <label>
                Depo / Lokasyon
                <select name="location_id" required>
                    <option value="">Seçiniz</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->getKey() }}" @selected((string) old('location_id') === (string) $location->getKey())>
                            {{ $location->warehouse->code }} — {{ $location->code }} / {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($errors->any())
            <div class="form-errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="page-actions">
            <span></span>
            <button class="button-primary" type="submit">Sayımı Başlat</button>
        </div>
    </form>
@endsection
