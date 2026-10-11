<div class="stack v65-product-detail">
    @php
        $tr = \App\Support\Formatting\TrFormatter::class;
        $physical = (string) ($summary?->getAttribute('physical') ?? '0');
        $reserved = (string) ($summary?->getAttribute('reserved_total') ?? '0');
        $available = ($product->kind->value === 'set'
            ? $product->setAvailability()
            : (string) ($summary?->getAttribute('available_total') ?? '0')) ?? '0';
        $quarantined = (string) ($summary?->getAttribute('quarantined') ?? '0');
        $unit = $product->unit?->name ?? '';
        $firstImage = $attachments->first(fn ($attachment) => str_starts_with((string) $attachment->mime, 'image/'));
    @endphp

    <nav class="form-row" aria-label="Ürün gezinme">
        <a href="{{ route('products.index') }}">← Ürün Listesi</a>
        @can('products.update')
            <a class="button-link" href="{{ route('products.edit', ['product' => $product->id]) }}">Ürünü Düzenle</a>
        @endcan
    </nav>

    <nav class="tabs v65-product-tabs" role="tablist" aria-label="Ürün detay sekmeleri">
        @foreach($tabs as $key => $label)
            <button type="button" role="tab" @class(['tab', 'is-active' => $activeTab === $key])
                aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                wire:click="selectTab('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </nav>

    @if($activeTab === 'overview')
    <section class="product-identity" aria-label="Ürün kimliği">
        <div class="product-photo">
            @if($firstImage)
                <img src="{{ route('products.images.show', ['product' => $product->id, 'attachment' => $firstImage->id]) }}" alt="{{ $product->name }}" loading="lazy">
            @else
                <span aria-hidden="true">◈</span>
            @endif
        </div>
        <div class="product-main">
            <h2>{{ $product->code }} · {{ $product->name }}</h2>
            <div class="sub">{{ $product->brand?->name ?? 'Markasız' }} · {{ $product->category?->name ?? 'Kategorisiz' }} · {{ $unit }}</div>
            <div class="form-row">
                <span class="badge">{{ $product->is_active ? 'Aktif' : 'Pasif' }}</span>
                <span class="badge">KDV %{{ number_format((float) $product->vat_rate, 2, ',', '.') }}</span>
                @if($product->barcode)<span class="badge">Barkod: {{ $product->barcode }}</span>@endif
            </div>
        </div>
        <div class="product-summary">
            <div><small>Satış Fiyatı</small><b>{{ $tr::money((string) $product->list_price) }} {{ $product->currency }}</b></div>
            <div><small>Alış Fiyatı</small><b>{{ $canViewCost && $cost ? $tr::money((string) $cost->last_purchase_price).' '.$product->currency : '—' }}</b></div>
            <div><small>Mevcut Stok</small><b>{{ $canViewStock ? $tr::quantity($physical).' '.$unit : '—' }}</b></div>
            <div><small>Kullanılabilir</small><b>{{ $canViewStock ? $tr::quantity((string) $available).' '.$unit : '—' }}</b></div>
        </div>
    </section>
    @endif

    @if($activeTab === 'overview')
        <div class="dashboard-grid">
            @can('stock.view')
                <div class="dashboard-card panel stack"><small>Mevcut Stok</small><strong>{{ $tr::quantity($physical) }} {{ $unit }}</strong><small>Tüm depolar</small></div>
            <div class="dashboard-card panel stack"><small>Rezerve</small><strong>{{ $tr::quantity($reserved) }} {{ $unit }}</strong><small>Siparişler için ayrılan</small></div>
            <div class="dashboard-card panel stack"><small>Kullanılabilir</small><strong>{{ $tr::quantity((string) $available) }} {{ $unit }}</strong><small>Satılabilir stok</small></div>
            @endcan
            @can('sales_invoices.view')
                <div class="dashboard-card panel stack"><small>{{ $currentYear }} Satış</small><strong>{{ $tr::quantity((string) ($salesStats?->quantity ?? '0')) }} {{ $unit }}</strong><small>{{ $tr::money((string) ($salesStats?->revenue ?? '0')) }} {{ $product->currency }} ciro</small></div>
            @endcan
            @can('supplier_invoices.view')
                <div class="dashboard-card panel stack"><small>Son Alış</small><strong>{{ $purchaseStats?->last_quantity !== null ? $tr::quantity((string) $purchaseStats->last_quantity).' '.$unit : '—' }}</strong><small>{{ $purchaseStats?->last_purchase_date ? \Illuminate\Support\Carbon::parse($purchaseStats->last_purchase_date)->format('d.m.Y') : 'Kesinleşmiş alış yok' }}</small></div>
            @endcan
        </div>
    @elseif($activeTab === 'stock')
        <div class="dashboard-grid">
            <div class="dashboard-card panel stack"><small>Toplam Stok</small><strong>{{ $tr::quantity($physical) }} {{ $unit }}</strong></div>
            <div class="dashboard-card panel stack"><small>Rezerve</small><strong>{{ $tr::quantity($reserved) }} {{ $unit }}</strong></div>
            <div class="dashboard-card panel stack"><small>Kullanılabilir</small><strong>{{ $tr::quantity((string) $available) }} {{ $unit }}</strong></div>
            <div class="dashboard-card panel stack"><small>Minimum Stok</small><strong>{{ $tr::quantity((string) $product->min_stock) }} {{ $unit }}</strong></div>
            <div class="dashboard-card panel stack"><small>Karantina</small><strong>{{ $tr::quantity($quarantined) }} {{ $unit }}</strong></div>
        </div>
    @elseif($activeTab === 'buyers')
        <div class="dashboard-grid">
            <div class="dashboard-card panel stack"><small>{{ $currentYear }} Satılan</small><strong>{{ $tr::quantity((string) ($salesStats?->quantity ?? '0')) }} {{ $unit }}</strong></div>
            <div class="dashboard-card panel stack"><small>{{ $currentYear }} Ciro</small><strong>{{ $tr::money((string) ($salesStats?->revenue ?? '0')) }} {{ $product->currency }}</strong><small>Ürün para birimiyle kesilen belgeler</small></div>
            <div class="dashboard-card panel stack"><small>Farklı Cari</small><strong>{{ $salesStats?->buyer_count ?? 0 }}</strong><small>{{ $currentYear }}</small></div>
            <div class="dashboard-card panel stack"><small>En Büyük Müşteri</small><strong>{{ $topBuyers->first()?->contact_name ?? '—' }}</strong><small>Tüm dönemler</small></div>
            <div class="dashboard-card panel stack"><small>Ortalama Satış Fiyatı</small>
                <strong>{{ bccomp((string) ($salesStats?->currency_quantity ?? '0'), '0', 3) > 0 ? $tr::money(bcdiv((string) ($salesStats?->revenue ?? '0'), (string) $salesStats->currency_quantity, 4)).' '.$product->currency : '—' }}</strong>
                <small>{{ $product->currency }} para birimindeki satışlardan</small>
            </div>
        </div>
    @elseif($activeTab === 'annual')
        <div class="dashboard-grid">
            <div class="dashboard-card panel stack"><small>{{ $currentYear }} Satış</small><strong>{{ $tr::quantity((string) ($salesStats?->quantity ?? '0')) }} {{ $unit }}</strong></div>
            <div class="dashboard-card panel stack"><small>{{ $currentYear }} Ciro</small><strong>{{ $tr::money((string) ($salesStats?->revenue ?? '0')) }} {{ $product->currency }}</strong></div>
            <div class="dashboard-card panel stack"><small>Toplam Satış Belgesi</small><strong>{{ $salesStats?->document_count ?? 0 }}</strong></div>
            <div class="dashboard-card panel stack"><small>Farklı Cari</small><strong>{{ $salesStats?->buyer_count ?? 0 }}</strong></div>
        </div>
    @endif

    @if($activeTab === 'overview')
        <div class="workbench">
            <div class="stack">
                @can('stock.view')
                <section class="panel stack">
                    <div class="form-row"><strong>Son Stok Hareketleri</strong>
                        @can('stock.view')<a href="{{ route('stock.movements', ['product' => $product->id]) }}">Tüm Hareketler →</a>@endcan
                    </div>
                    <div class="table-scroll v65-grid-scroll"><table class="data-table">
                        <thead><tr><th>Tarih</th><th>Belge</th><th>İşlem</th><th>Giriş</th><th>Çıkış</th><th>Bakiye</th><th>Depo</th></tr></thead>
                        <tbody>
                        @forelse($movements as $movement)
                            <tr><td>{{ $movement->movement_date?->format('d.m.Y') }}</td>
                                <td>{{ $movement->document_no ?? '—' }}</td>
                                <td>{{ $movement->reason }}</td>
                                <td>{{ $movement->direction === 'in' ? $tr::quantity((string) $movement->quantity) : '—' }}</td>
                                <td>{{ $movement->direction === 'out' ? $tr::quantity((string) $movement->quantity) : '—' }}</td>
                                <td>{{ $tr::quantity((string) $movement->balance_after) }}</td>
                                <td>{{ $movement->location?->name ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="empty-state">Bu ürün için stok hareketi bulunamadı.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                </section>
                @endcan
                @can('sales_invoices.view')
                    <section class="panel stack">
                        <strong>Son Satışlar</strong>
                        <div class="table-scroll v65-grid-scroll"><table class="data-table">
                            <thead><tr><th>Tarih</th><th>Cari</th><th>Belge</th><th>Miktar</th><th>Birim Fiyat</th></tr></thead>
                            <tbody>
                            @forelse($sales as $line)
                                <tr><td>{{ $line->document?->document_date?->format('d.m.Y') }}</td>
                                    <td>{{ $line->document?->contact?->title ?? '—' }}</td>
                                    <td>{{ $line->document?->number ?? '—' }}</td>
                                    <td>{{ $tr::quantity((string) $line->quantity) }}</td>
                                    <td>{{ $tr::money((string) $line->unit_price) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">Kesinleşmiş satış kaydı yok.</td></tr>
                            @endforelse
                            </tbody>
                        </table></div>
                    </section>
                @endcan
            </div>
            <aside class="stack">
                <section class="panel stack">
                    <strong>Hızlı İşlemler</strong>
                    @can('quotes.create')<a href="{{ route('sales.quotes.edit') }}">Teklif Oluştur</a>@endcan
                    @can('sales_orders.create')<a href="{{ route('sales.orders.edit') }}">Sipariş Oluştur</a>@endcan
                    @can('sales_invoices.create')<a href="{{ route('sales.invoices.edit') }}">Satış Faturası</a>@endcan
                    @can('stock.view')<a href="{{ route('stock.movements', ['product' => $product->id]) }}">Stok Hareketleri</a>@endcan
                    @can('stock_counts.view')<a href="{{ route('stock.counts.index') }}">Stok Sayımları</a>@endcan
                    @can('products.update')<a href="{{ route('products.edit', ['product' => $product->id]) }}">Ürünü Düzenle</a>@endcan
                </section>
                @can('stock.view')
                <section class="panel stack">
                    <strong>Stok Uyarısı</strong>
                    <span>Minimum: {{ $tr::quantity((string) $product->min_stock) }}</span>
                    <span>Kullanılabilir: {{ $tr::quantity((string) $available) }}</span>
                    <span class="badge">{{ bccomp((string) $available, (string) $product->min_stock, 3) > 0 ? 'Stok Yeterli' : 'Stok Kritik / Tükendi' }}</span>
                </section>
                @endcan
            </aside>
        </div>
    @elseif(in_array($activeTab, ['stock', 'locations'], true))
        <section class="panel stack">
            <strong>Depo Bazlı Stok</strong>
            <div class="table-scroll v65-grid-scroll"><table class="data-table">
                <thead><tr><th>Depo</th><th>Mevcut</th><th>Rezerve</th><th>Karantina</th><th>Kullanılabilir</th></tr></thead>
                <tbody>
                @forelse($balances as $balance)
                    <tr><td>{{ $balance->location?->name ?? '—' }}</td>
                        <td>{{ $tr::quantity((string) $balance->quantity) }}</td>
                        <td>{{ $tr::quantity((string) bcadd((string) $balance->reserved, (string) $balance->consignment_reserved, 3)) }}</td>
                        <td>{{ $tr::quantity((string) $balance->quarantine) }}</td>
                        <td>{{ $tr::quantity($balance->available()) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Depo bazında stok kaydı bulunamadı.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @elseif($activeTab === 'movements')
        <section class="panel stack">
            <div class="form-row"><strong>Stok Hareketleri</strong>
                @can('stock.view')<a href="{{ route('stock.movements', ['product' => $product->id]) }}">Tüm Hareketler →</a>@endcan
            </div>
            <div class="table-scroll v65-grid-scroll"><table class="data-table">
                <thead><tr><th>Tarih</th><th>Belge</th><th>Hareket</th><th>Depo</th><th>Yön</th><th>Miktar</th><th>Kalan</th></tr></thead>
                <tbody>
                @forelse($movements as $movement)
                    <tr><td>{{ $movement->movement_date?->format('d.m.Y') }}</td>
                        <td>{{ $movement->document_no ?? '—' }}</td><td>{{ $movement->reason }}</td>
                        <td>{{ $movement->location?->name ?? '—' }}</td>
                        <td>{{ $movement->direction === 'in' ? 'Giriş' : 'Çıkış' }}</td>
                        <td>{{ $tr::quantity((string) $movement->quantity) }}</td>
                        <td>{{ $tr::quantity((string) $movement->balance_after) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Henüz stok hareketi yok.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @elseif(in_array($activeTab, ['sales', 'purchases'], true))
        @php($lines = $activeTab === 'sales' ? $sales : $purchases)
        <section class="panel stack">
            <div class="form-row"><strong>{{ $activeTab === 'sales' ? 'Ürün Satışları' : 'Ürün Alışları' }}</strong><span>{{ $product->code }} · {{ $product->name }}</span></div>
            <div class="table-scroll v65-grid-scroll"><table class="data-table">
                <thead><tr><th>Tarih</th><th>{{ $activeTab === 'sales' ? 'Cari' : 'Tedarikçi' }}</th><th>Belge</th><th>Miktar</th><th>Birim Fiyat</th><th>{{ $activeTab === 'sales' ? 'İskonto' : 'Para Birimi' }}</th><th>Net Tutar</th></tr></thead>
                <tbody>
                @forelse($lines as $line)
                    <tr><td>{{ $line->document?->document_date?->format('d.m.Y') }}</td>
                        <td>{{ $line->document?->contact?->title ?? '—' }}</td>
                        <td>
                            @if($activeTab === 'sales')
                                <a href="{{ route('sales.invoices.edit', ['id' => $line->document_id]) }}">{{ $line->document?->number ?? '—' }}</a>
                            @else
                                <a href="{{ route('purchases.invoices.edit', ['id' => $line->document_id]) }}">{{ $line->document?->number ?? '—' }}</a>
                            @endif
                        </td>
                        <td>{{ $tr::quantity((string) $line->quantity) }}</td>
                        <td>{{ $activeTab === 'sales' || $canViewCost ? $tr::money((string) $line->unit_price) : '—' }}</td>
                        <td>{{ $activeTab === 'sales' ? '%'.number_format((float) $line->line_discount_rate, 2, ',', '.') : ($line->document?->currency ?? '—') }}</td>
                        <td>{{ $activeTab === 'sales' || $canViewCost ? $tr::money((string) $line->line_total) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Bu ürüne ait kesinleşmiş belge bulunamadı.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @elseif($activeTab === 'buyers')
        <section class="panel stack">
            <strong>En Çok Alan Cariler</strong>
            <small>Ciro yalnız ürün para birimindeki ({{ $product->currency }}) kesinleşmiş satışları içerir. Miktar tüm para birimlerini kapsar.</small>
            <div class="table-scroll v65-grid-scroll"><table class="data-table">
                <thead><tr><th>Sıra</th><th>Cari</th><th>Toplam Adet</th><th>Ciro ({{ $product->currency }})</th><th>Son Alım</th><th>Ortalama Fiyat</th></tr></thead>
                <tbody>
                    @forelse($topBuyers as $buyer)
                        <tr><td>{{ $loop->iteration }}</td>
                            <td>{{ $buyer->contact_name }}</td>
                            <td>{{ $tr::quantity((string) $buyer->quantity) }} {{ $unit }}</td>
                            <td>{{ $tr::money((string) $buyer->revenue) }}</td>
                            <td>{{ $buyer->last_purchase_at ? \Illuminate\Support\Carbon::parse($buyer->last_purchase_at)->format('d.m.Y') : '—' }}</td>
                            <td>{{ bccomp((string) $buyer->currency_quantity, '0', 3) > 0 ? $tr::money(bcdiv((string) $buyer->revenue, (string) $buyer->currency_quantity, 4)) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Kesinleşmiş satış kaydı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
    @elseif($activeTab === 'annual')
        <section class="panel stack">
            <strong>Yıllık Performans · Kesinleşmiş Satışlar</strong>
            <small>Ciro {{ $product->currency }} para birimiyle kesilen belgeleri içerir; farklı para birimlerindeki tutarlar birleştirilmez.</small>
            <div class="table-scroll v65-grid-scroll"><table class="data-table">
                <thead><tr><th>Yıl</th><th>Toplam Adet</th><th>Ciro ({{ $product->currency }})</th><th>Belge Sayısı</th></tr></thead>
                <tbody>
                    @forelse($annual as $year)
                        <tr><td>{{ $year->year }}</td>
                            <td>{{ $tr::quantity((string) $year->quantity) }} {{ $unit }}</td>
                            <td>{{ $tr::money((string) $year->revenue) }}</td>
                            <td>{{ $year->documents }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Yıllık satış verisi yok.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
    @elseif($activeTab === 'prices')
        <section class="panel stack">
            <strong>Fiyatlar</strong>
            <div class="dashboard-grid">
                <div class="dashboard-card panel stack"><small>Satış Fiyatı</small><strong>{{ $tr::money((string) $product->list_price) }} {{ $product->currency }}</strong><small>Varsayılan</small></div>
                <div class="dashboard-card panel stack"><small>Son Alış</small><strong>{{ $canViewCost && $cost ? $tr::money((string) $cost->last_purchase_price).' '.$product->currency : '—' }}</strong></div>
                <div class="dashboard-card panel stack"><small>Hareketli Ortalama</small><strong>{{ $canViewCost && $cost ? $tr::money((string) $cost->moving_average).' '.$product->currency : '—' }}</strong></div>
                <div class="dashboard-card panel stack"><small>KDV</small><strong>%{{ number_format((float) $product->vat_rate, 2, ',', '.') }}</strong></div>
            </div>
            <div class="form-grid">
                <div class="field"><span class="field-label">Liste Fiyatı</span><strong>{{ $tr::money((string) $product->list_price) }} {{ $product->currency }}</strong></div>
                <div class="field"><span class="field-label">KDV</span><strong>%{{ $product->vat_rate }}</strong></div>
                @if($canViewCost && $cost)
                    <div class="field"><span class="field-label">Son Alış Maliyeti</span><strong>{{ $tr::money((string) $cost->last_purchase_price) }}</strong></div>
                    <div class="field"><span class="field-label">Hareketli Ortalama</span><strong>{{ $tr::money((string) $cost->moving_average) }}</strong></div>
                @endif
            </div>
            <div class="table-scroll v65-grid-scroll"><table class="data-table">
                <thead><tr><th>Fiyat Listesi</th><th>Fiyat</th><th>Başlangıç</th><th>Bitiş</th></tr></thead>
                <tbody>@forelse($prices as $item)
                    <tr><td>{{ $item->priceList?->name ?? '—' }}</td><td>{{ $tr::money((string) $item->price) }}</td>
                        <td>{{ $item->valid_from?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $item->valid_to?->format('d.m.Y') ?? '—' }}</td></tr>
                @empty<tr><td colspan="4" class="empty-state">Ek fiyat listesi kaydı yok.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    @elseif($activeTab === 'technical')
        <section class="panel stack">
            <strong>Teknik Bilgi Föyü</strong>
            <p>Ürüne ait teknik bilgiler ve ilişkili dokümanlar burada gösterilir. Bağımsız teknik föy şablonu mevcut veri modelinde bulunmuyor.</p>
            @if($product->description)
                <div class="field"><span class="field-label">Ürün Açıklaması</span><p class="readonly-note">{{ $product->description }}</p></div>
            @endif
            <div class="stack">
                @forelse($attachments->filter(fn ($row) => str_contains(mb_strtolower($row->original_name), 'teknik') || str_contains(mb_strtolower($row->original_name), 'technical')) as $file)
                    <div class="form-row"><span>{{ $file->original_name }}</span><small>{{ $file->mime }}</small></div>
                @empty
                    <p class="empty-state">Ürüne bağlı teknik doküman bulunamadı.</p>
                @endforelse
            </div>
        </section>
    @elseif($activeTab === 'variants')
        <section class="panel stack"><strong>Varyant Özellikleri</strong>
            <div class="domain-info-grid">
                @forelse($variants as $variant)
                    <div><small>{{ $variant->attribute?->name ?? 'Özellik' }}</small><b>{{ $variant->value }}</b></div>
                @empty
                    <p class="empty-state">Varyant özelliği tanımlanmamış.</p>
                @endforelse
            </div>
        </section>
    @elseif($activeTab === 'barcodes')
        <section class="panel stack"><strong>Barkodlar</strong>
            <div class="domain-info-grid">
                <div><small>Ana Barkod</small><b>{{ $product->barcode ?: 'Barkod tanımlanmamış' }}</b></div>
                <div><small>Ürün Kodu</small><b>{{ $product->code }}</b></div>
            </div>
        </section>
    @elseif($activeTab === 'suppliers')
        <section class="panel stack">
            <strong>Ürün Tedarikçileri · Kesinleşmiş Alışlar</strong>
            <div class="table-scroll v65-grid-scroll"><table class="data-table">
                <thead><tr><th>Tedarikçi</th><th>Toplam Alış</th><th>Belge Sayısı</th><th>Son Alış</th></tr></thead>
                <tbody>
                    @forelse($topSuppliers as $supplier)
                        <tr><td>{{ $supplier->contact_name }}</td>
                            <td>{{ $tr::quantity((string) $supplier->quantity) }} {{ $unit }}</td>
                            <td>{{ $supplier->documents }}</td>
                            <td>{{ $supplier->last_purchase_at ? \Illuminate\Support\Carbon::parse($supplier->last_purchase_at)->format('d.m.Y') : '—' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Bu ürün için alış yapılan tedarikçi bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
    @elseif($activeTab === 'images')
        <section class="panel stack"><strong>Ürün Görselleri</strong>
            <div class="image-grid">
                @forelse($attachments->filter(fn ($row) => str_starts_with((string) $row->mime, 'image/')) as $image)
                    <div class="image-card"><img class="product-image-preview"
                        src="{{ route('products.images.show', ['product' => $product->id, 'attachment' => $image->id]) }}"
                        alt="{{ $image->original_name }}" loading="lazy"><small>{{ $image->original_name }}</small></div>
                @empty
                    <p class="empty-state">Bu ürün için görsel yüklenmemiş.</p>
                @endforelse
            </div>
            @can('products.update')<a href="{{ route('products.edit', ['product' => $product->id]) }}">Görselleri Düzenle →</a>@endcan
        </section>
    @elseif($activeTab === 'notes')
        <section class="panel stack"><strong>Açıklama / Notlar</strong>
            <p class="readonly-note">{{ $product->description ?: 'Ürün açıklaması bulunmuyor.' }}</p>
        </section>
    @elseif($activeTab === 'files')
        <section class="panel stack"><strong>Ürün Dosyaları</strong>
            <div class="stack">
                @forelse($attachments as $file)
                    <div class="form-row">
                        <span>{{ $file->original_name }}</span>
                        <small>{{ $file->collection }} · {{ $file->mime }}</small>
                    </div>
                @empty
                    <p class="empty-state">Ekli dosya bulunamadı.</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
