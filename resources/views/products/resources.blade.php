@extends('layouts.app')

@section('title', 'Ürün Kaynakları')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Kaynaklar</p>
            <h1>{{ $product->name }}</h1>
            <p>{{ $product->code }} · Tedarikçi, teknik dosya ve medya ilişkileri</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.products.show', $product->getKey()) }}" data-workspace-link>Ürün Detay</a>
            <a href="{{ route('inventory.index') }}" data-workspace-link>Ürünler</a>
        </div>
    </section>

    <section class="detail-card">
        <h2>Tedarikçiler</h2>
        @if ($product->supplierRelations->isEmpty())
            <p>Bu ürüne bağlı tedarikçi yok.</p>
        @else
            <table class="data-table">
                <thead>
                <tr>
                    <th>Kod</th>
                    <th>Tedarikçi</th>
                    <th>Durum</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($product->supplierRelations as $relation)
                    <tr>
                        <td>{{ $relation->account->code }}</td>
                        <td>{{ $relation->account->legal_name }}</td>
                        <td>{{ $relation->account->statusEnum()->label() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @can('products.manage')
            <form method="post" action="{{ route('inventory.products.resources.suppliers.update', $product->getKey()) }}">
                @csrf
                @method('PUT')
                <label>
                    Tedarikçi Carileri
                    <select name="supplier_ids[]" multiple size="10">
                        @foreach ($supplierAccounts as $supplier)
                            <option value="{{ $supplier->getKey() }}" @selected(in_array($supplier->getKey(), old('supplier_ids', $selectedSupplierIds), true))>
                                {{ $supplier->code }} · {{ $supplier->legal_name }}{{ $supplier->isActive() ? '' : ' · Pasif' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <p class="field-hint">Yalnız Supplier veya Mixed tipindeki cariler kullanılabilir. Pasif mevcut ilişki korunabilir; pasif cari yeni ilişki olarak eklenemez.</p>
                <div class="page-actions">
                    <span></span>
                    <button type="submit">Tedarikçileri Kaydet</button>
                </div>
            </form>
        @endcan
    </section>

    <section class="detail-card">
        <h2>Teknik Dosyalar</h2>
        <p>Teknik föy, montaj dokümanı, ölçü çizimi veya ürünle ilişkili güvenli dokümanlar.</p>

        <table class="data-table">
            <thead>
            <tr>
                <th>Etiket</th>
                <th>Dosya</th>
                <th>Tür</th>
                <th>Boyut</th>
                <th>İşlem</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($productFiles->filter(fn ($productFile) => $productFile->getRawOriginal('kind') === 'technical') as $productFile)
                <tr>
                    <td>{{ $productFile->attachment->label ?? 'Teknik Dosya' }}</td>
                    <td>{{ $productFile->attachment->fileAsset->original_name }}</td>
                    <td>{{ $productFile->attachment->fileAsset->mime_type }}</td>
                    <td>{{ number_format(((int) $productFile->attachment->fileAsset->size_bytes) / 1024, 1, ',', '.') }} KB</td>
                    <td>
                        <a href="{{ route('inventory.products.resources.files.download', [$product->getKey(), $productFile->getKey()]) }}">İndir</a>
                        @can('products.manage')
                            <form method="post" action="{{ route('inventory.products.resources.files.detach', [$product->getKey(), $productFile->getKey()]) }}" class="inline-form">
                                @csrf
                                <button type="submit">Bağlantıyı Kaldır</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Teknik dosya yok.</td></tr>
            @endforelse
            </tbody>
        </table>

        @can('products.manage')
            <form method="post" enctype="multipart/form-data" action="{{ route('inventory.products.resources.files.store', $product->getKey()) }}">
                @csrf
                <input type="hidden" name="kind" value="technical">
                <div class="form-grid">
                    <label>
                        Dosya
                        <input type="file" name="file" required>
                    </label>
                    <label>
                        Etiket
                        <input type="text" name="label" maxlength="160" placeholder="Örn. Montaj föyü">
                    </label>
                </div>
                <div class="page-actions"><span></span><button type="submit">Teknik Dosya Yükle</button></div>
            </form>
        @endcan
    </section>

    <section class="detail-card">
        <h2>Medya</h2>
        <p>Bu foundation aşamasında medya yalnız doğrulanmış görsel dosyasıdır. Ana görsel/galeri/destination-set işlemleri M21 kapsamındadır.</p>

        <table class="data-table">
            <thead>
            <tr>
                <th>Etiket</th>
                <th>Dosya</th>
                <th>Tür</th>
                <th>Boyut</th>
                <th>İşlem</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($productFiles->filter(fn ($productFile) => $productFile->getRawOriginal('kind') === 'media') as $productFile)
                <tr>
                    <td>{{ $productFile->attachment->label ?? 'Medya' }}</td>
                    <td>{{ $productFile->attachment->fileAsset->original_name }}</td>
                    <td>{{ $productFile->attachment->fileAsset->mime_type }}</td>
                    <td>{{ number_format(((int) $productFile->attachment->fileAsset->size_bytes) / 1024, 1, ',', '.') }} KB</td>
                    <td>
                        <a href="{{ route('inventory.products.resources.files.download', [$product->getKey(), $productFile->getKey()]) }}">İndir</a>
                        @can('products.manage')
                            <form method="post" action="{{ route('inventory.products.resources.files.detach', [$product->getKey(), $productFile->getKey()]) }}" class="inline-form">
                                @csrf
                                <button type="submit">Bağlantıyı Kaldır</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Medya dosyası yok.</td></tr>
            @endforelse
            </tbody>
        </table>

        @can('products.manage')
            <form method="post" enctype="multipart/form-data" action="{{ route('inventory.products.resources.files.store', $product->getKey()) }}">
                @csrf
                <input type="hidden" name="kind" value="media">
                <div class="form-grid">
                    <label>
                        Görsel
                        <input type="file" name="file" accept="image/*" required>
                    </label>
                    <label>
                        Etiket
                        <input type="text" name="label" maxlength="160" placeholder="Örn. Ürün görseli">
                    </label>
                </div>
                <div class="page-actions"><span></span><button type="submit">Medya Yükle</button></div>
            </form>
        @endcan
    </section>
@endsection
