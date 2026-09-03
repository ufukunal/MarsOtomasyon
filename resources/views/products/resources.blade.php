@extends('layouts.app')

@section('title', 'Ürün Kaynakları')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Kaynaklar</p>
            <h1>{{ $product->name }}</h1>
            <p>{{ $product->code }} · Tedarikçi, teknik dosya ve ürün görsel operasyonları</p>
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

    @php($mediaFiles = $productFiles->filter(fn ($productFile) => $productFile->getRawOriginal('kind') === 'media')->values())

    <section class="detail-card">
        <h2>Ürün Görselleri</h2>
        <p>Ana görsel, galeri sırası, site/kanal hedef kümeleri, tahribatsız crop/rotate/flip/resize reçetesi, provider doğrulama metadata bilgisi, kopyala/taşı ve karantina lifecycle'ı bu ekrandan yönetilir.</p>
        <p class="field-hint">Dönüşüm alanları orijinal private dosyayı değiştirmez. Kaydedilen reçete render/provider katmanında uygulanmak üzere metadata olarak tutulur.</p>

        <table class="data-table">
            <thead>
            <tr>
                <th>Sıra</th>
                <th>Ana</th>
                <th>Dosya</th>
                <th>Hedefler</th>
                <th>Provider</th>
                <th>Güvenlik</th>
                <th>İşlem</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($mediaFiles as $productFile)
                @php($asset = $productFile->attachment->fileAsset)
                @php($providerValidation = is_array($productFile->provider_validation) ? $productFile->provider_validation : [])
                <tr>
                    <td>{{ $productFile->position }}</td>
                    <td>{{ $productFile->is_main ? 'Ana' : 'Galeri' }}</td>
                    <td>
                        {{ $productFile->attachment->label ?? 'Medya' }}<br>
                        <small>{{ $asset->original_name }} · {{ number_format(((int) $asset->size_bytes) / 1024, 1, ',', '.') }} KB</small>
                    </td>
                    <td>{{ implode(', ', is_array($productFile->destinations) ? $productFile->destinations : []) ?: '—' }}</td>
                    <td>
                        @if ($providerValidation !== [])
                            {{ $providerValidation['provider'] ?? '—' }} · {{ $providerValidation['status'] ?? '—' }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($asset->quarantined_at !== null)
                            Karantina<br><small>{{ $asset->quarantine_reason }}</small>
                        @else
                            Aktif
                        @endif
                    </td>
                    <td>
                        @if ($asset->quarantined_at === null)
                            <a href="{{ route('inventory.products.resources.files.download', [$product->getKey(), $productFile->getKey()]) }}">İndir</a>
                        @endif
                        @can('products.manage')
                            @if (! $productFile->is_main && $asset->quarantined_at === null)
                                <form method="post" action="{{ route('inventory.products.resources.media.main', [$product->getKey(), $productFile->getKey()]) }}" class="inline-form">
                                    @csrf
                                    <button type="submit">Ana Yap</button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('inventory.products.resources.files.detach', [$product->getKey(), $productFile->getKey()]) }}" class="inline-form">
                                @csrf
                                <button type="submit">Bağlantıyı Kaldır</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Medya dosyası yok.</td></tr>
            @endforelse
            </tbody>
        </table>

        @can('products.manage')
            @if ($mediaFiles->isNotEmpty())
                <form method="post" action="{{ route('inventory.products.resources.media.order', $product->getKey()) }}">
                    @csrf
                    @method('PUT')
                    <h3>Galeri Sırası</h3>
                    <div class="form-grid">
                        @foreach ($mediaFiles as $productFile)
                            <label>
                                {{ $productFile->attachment->label ?? $productFile->attachment->fileAsset->original_name }}
                                <input type="number" name="positions[{{ $productFile->getKey() }}]" min="0" max="32767" value="{{ $productFile->position }}" required>
                            </label>
                        @endforeach
                    </div>
                    <div class="page-actions"><span></span><button type="submit">Galeri Sırasını Kaydet</button></div>
                </form>
            @endif

            <hr>
            <h3>Yeni Görsel</h3>
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
                        <input type="text" name="label" maxlength="160" placeholder="Örn. Ön görünüş">
                    </label>
                </div>
                <div class="page-actions"><span></span><button type="submit">Medya Yükle</button></div>
            </form>
        @endcan
    </section>

    @can('products.manage')
        @foreach ($mediaFiles as $productFile)
            @php($asset = $productFile->attachment->fileAsset)
            @php($transform = is_array($productFile->transform_metadata) ? $productFile->transform_metadata : [])
            @php($crop = is_array($transform['crop'] ?? null) ? $transform['crop'] : [])
            @php($flip = is_array($transform['flip'] ?? null) ? $transform['flip'] : [])
            @php($resize = is_array($transform['resize'] ?? null) ? $transform['resize'] : [])
            @php($providerValidation = is_array($productFile->provider_validation) ? $productFile->provider_validation : [])

            <section class="detail-card">
                <h2>Görsel Operasyonları · {{ $productFile->attachment->label ?? $asset->original_name }}</h2>
                <p>{{ $asset->original_name }} · {{ $productFile->is_main ? 'Ana görsel' : 'Galeri' }} · sıra {{ $productFile->position }}</p>

                @if ($asset->quarantined_at !== null)
                    <p><strong>Karantina:</strong> {{ $asset->quarantine_reason }}</p>
                    <form method="post" action="{{ route('inventory.products.resources.media.release-quarantine', [$product->getKey(), $productFile->getKey()]) }}">
                        @csrf
                        <div class="page-actions"><span></span><button type="submit">Karantinadan Çıkar</button></div>
                    </form>
                @else
                    <h3>Site / Kanal Hedef Kümeleri</h3>
                    <form method="post" action="{{ route('inventory.products.resources.media.destinations', [$product->getKey(), $productFile->getKey()]) }}">
                        @csrf
                        @method('PUT')
                        <label>
                            Hedefler
                            <input type="text" name="destinations" maxlength="4096" value="{{ implode(', ', is_array($productFile->destinations) ? $productFile->destinations : []) }}" placeholder="site, trendyol, amazon">
                        </label>
                        <p class="field-hint">Virgül, noktalı virgül veya boşlukla ayır. En fazla 32 hedef kimliği.</p>
                        <div class="page-actions"><span></span><button type="submit">Hedefleri Kaydet</button></div>
                    </form>

                    <h3>Tahribatsız Görsel Düzenleme Reçetesi</h3>
                    <form method="post" action="{{ route('inventory.products.resources.media.transform', [$product->getKey(), $productFile->getKey()]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="flip_present" value="1">
                        <div class="form-grid">
                            <label>Crop X<input type="number" name="crop_x" min="0" max="100000" value="{{ $crop['x'] ?? '' }}"></label>
                            <label>Crop Y<input type="number" name="crop_y" min="0" max="100000" value="{{ $crop['y'] ?? '' }}"></label>
                            <label>Crop Genişlik<input type="number" name="crop_width" min="1" max="100000" value="{{ $crop['width'] ?? '' }}"></label>
                            <label>Crop Yükseklik<input type="number" name="crop_height" min="1" max="100000" value="{{ $crop['height'] ?? '' }}"></label>
                            <label>
                                Döndür
                                <select name="rotate">
                                    @foreach ([0, 90, 180, 270] as $rotation)
                                        <option value="{{ $rotation }}" @selected((int) ($transform['rotate'] ?? 0) === $rotation)>{{ $rotation }}°</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Resize Genişlik<input type="number" name="resize_width" min="1" max="100000" value="{{ $resize['width'] ?? '' }}"></label>
                            <label>Resize Yükseklik<input type="number" name="resize_height" min="1" max="100000" value="{{ $resize['height'] ?? '' }}"></label>
                            <label>
                                Resize Modu
                                <select name="resize_mode">
                                    @foreach (['contain', 'cover', 'stretch'] as $mode)
                                        <option value="{{ $mode }}" @selected(($resize['mode'] ?? 'contain') === $mode)>{{ $mode }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label><input type="checkbox" name="flip_horizontal" value="1" @checked((bool) ($flip['horizontal'] ?? false))> Yatay çevir</label>
                        <label><input type="checkbox" name="flip_vertical" value="1" @checked((bool) ($flip['vertical'] ?? false))> Dikey çevir</label>
                        <div class="page-actions"><span></span><button type="submit">Dönüşüm Reçetesini Kaydet</button></div>
                    </form>

                    <h3>Provider Doğrulama Metadata</h3>
                    <form method="post" action="{{ route('inventory.products.resources.media.provider-validation', [$product->getKey(), $productFile->getKey()]) }}">
                        @csrf
                        @method('PUT')
                        <div class="form-grid">
                            <label>
                                Provider
                                <input type="text" name="provider" maxlength="80" value="{{ $providerValidation['provider'] ?? '' }}" placeholder="trendyol">
                            </label>
                            <label>
                                Durum
                                <select name="status">
                                    @foreach (['pending', 'valid', 'warning', 'invalid'] as $status)
                                        <option value="{{ $status }}" @selected(($providerValidation['status'] ?? 'pending') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label>
                            Mesajlar
                            <textarea name="messages" rows="3" maxlength="10000">{{ implode("\n", is_array($providerValidation['messages'] ?? null) ? $providerValidation['messages'] : []) }}</textarea>
                        </label>
                        <div class="page-actions"><span></span><button type="submit">Provider Metadata Kaydet</button></div>
                    </form>

                    @if ($mediaTargetProducts->isNotEmpty())
                        <h3>Kopyala / Taşı</h3>
                        <div class="form-grid">
                            <form method="post" action="{{ route('inventory.products.resources.media.copy', [$product->getKey(), $productFile->getKey()]) }}">
                                @csrf
                                <label>
                                    Hedef Ürün
                                    <select name="target_product_id" required>
                                        <option value="">Seç</option>
                                        @foreach ($mediaTargetProducts as $targetProduct)
                                            <option value="{{ $targetProduct->getKey() }}">{{ $targetProduct->code }} · {{ $targetProduct->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button type="submit">Görseli Kopyala</button>
                            </form>
                            <form method="post" action="{{ route('inventory.products.resources.media.move', [$product->getKey(), $productFile->getKey()]) }}">
                                @csrf
                                <label>
                                    Hedef Ürün
                                    <select name="target_product_id" required>
                                        <option value="">Seç</option>
                                        @foreach ($mediaTargetProducts as $targetProduct)
                                            <option value="{{ $targetProduct->getKey() }}">{{ $targetProduct->code }} · {{ $targetProduct->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button type="submit">Görseli Taşı</button>
                            </form>
                        </div>
                    @endif

                    <h3>Güvenlik / Karantina</h3>
                    <form method="post" action="{{ route('inventory.products.resources.media.quarantine', [$product->getKey(), $productFile->getKey()]) }}">
                        @csrf
                        <label>
                            Karantina Nedeni
                            <input type="text" name="reason" maxlength="255" required placeholder="Örn. Malware scan manual review">
                        </label>
                        <div class="page-actions"><span></span><button type="submit">Karantinaya Al</button></div>
                    </form>
                @endif
            </section>
        @endforeach
    @endcan
@endsection
