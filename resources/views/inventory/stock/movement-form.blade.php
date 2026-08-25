@extends('layouts.app')

@section('title', 'Yeni Stok Hareketi')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Stok</p>
            <h1>Yeni Stok Hareketi</h1>
            <p>Pozitif girişte birim maliyet zorunludur. Çıkış mevcut moving-average taşıma maliyetiyle değerlenir; negatif stok bloke edilir.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.stock.movements') }}" data-workspace-link>Hareketler</a>
            <a href="{{ route('inventory.stock.index') }}" data-workspace-link>Bakiyeler</a>
        </div>
    </section>

    <form method="post" action="{{ route('inventory.stock.movements.store') }}" class="detail-card">
        @csrf
        <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">

        <div class="form-grid">
            <label>
                Ürün
                <select name="product_id" required>
                    <option value="">Seçiniz</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->getKey() }}" @selected((string) old('product_id') === (string) $product->getKey())>
                            {{ $product->code }} — {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </label>
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
            <label>
                Hareket Tipi
                <select name="movement_type" required>
                    @foreach ($movementKinds as $kind)
                        <option value="{{ $kind->value }}" @selected(old('movement_type', 'opening_in') === $kind->value)>{{ $kind->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Miktar
                <input type="number" name="quantity" min="0.000001" step="0.000001" value="{{ old('quantity') }}" required>
            </label>
            <label>
                Birim Maliyet
                <input type="number" name="unit_cost" min="0" step="0.000001" value="{{ old('unit_cost') }}">
                <small>Girişlerde zorunlu ve sıfırdan büyük. Çıkışta girilen değer kullanılmaz.</small>
            </label>
            <label>
                Not
                <input type="text" name="note" maxlength="240" value="{{ old('note') }}">
            </label>
        </div>

        @if ($errors->any())
            <div class="form-errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="page-actions">
            <span></span>
            <button class="button-primary" type="submit">Stok Hareketini İşle</button>
        </div>
    </form>
@endsection
